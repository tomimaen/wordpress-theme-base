<?php
/**
 * This file handles everything related to assets. Fonts, CSS & JavaScript.
 */
namespace Vincit\Assets;

define("THEMEROOT", get_stylesheet_directory());
define("CLIENT_MANIFEST", (array) json_decode(file_get_contents(THEMEROOT . "/dist/client-manifest.json")));
define("ADMIN_MANIFEST", (array) json_decode(file_get_contents(THEMEROOT . "/dist/admin-manifest.json")));
define("IS_WDS", !empty($_SERVER["HTTP_X_PROXIEDBY_WEBPACK"]) && $_SERVER["HTTP_X_PROXIEDBY_WEBPACK"] === 'true');

function asset_path($asset, $ignore_existence = false) {
  $path = get_stylesheet_directory_uri() . "/dist/";
  $notInClient = empty(CLIENT_MANIFEST[$asset]);
  $notInAdmin = empty(ADMIN_MANIFEST[$asset]);

  if ($notInClient && $notInAdmin) {
    error_log("Asset $asset wasn't found in any manifest.");
    return false;
  }

  error_log(print_r(CLIENT_MANIFEST, true));

  if (!$notInClient) {
    return $path . CLIENT_MANIFEST[$asset];
  }

  if (!$notInAdmin) {
    return $path . ADMIN_MANIFEST[$asset];
  }

  throw new \Exception("This code should've returned already. Bug.");
};

function enqueue_parts($path = null, $deps = []) {
  if (is_null($path)) {
    trigger_error('Enqueue path must not be empty', E_USER_ERROR);
  }

  $parts = explode(".", basename($path));
  $type = array_reverse($parts)[0];
  $handle = basename($parts[0]) . "-" . $type;

  // Some externals won't have filetype in the URL, so do manual override.
  if (strpos($path, "fonts.googleapis") > -1) {
    $type = "css";
    $handle = "fonts";
  } else if (strpos($path, "polyfill.io") > -1) {
    $type = "js";
    $handle = "polyfill";
  }

  return [
    "parts" => $parts,
    "type" => $type,
    "handle" => $handle,
    "file" => $path,
  ];
}

/**
 * Better enqueue function. Less verbose to use.
 *
 * @param string $path
 * @param array $deps
 * @param boolean $external
 */

function enqueue($path = null, $deps = []) {
  if (!$path) {
    return false;
  }

  $parts = enqueue_parts($path, $deps);
  $type = $parts["type"];
  $handle = $parts["handle"];
  $file = $parts["file"];

  switch ($type) {
    case "js":
      \wp_enqueue_script($handle, $file, $deps, false, true);
      break;
    case "css":
      \wp_enqueue_style($handle, $file, $deps, false, 'all');
  break;
    default:
      throw new \Exception('Enqueued file must be a css or js file.');
  }

  return $file;
}

function theme_assets() {
  // Webfonts:
  enqueue("https://fonts.googleapis.com/css?family=Source+Sans+Pro:400,700|Source+Serif+Pro:700", [], true);

  // Webpack generates a polyfill. If you'd rather use something like polyfill.io, uncomment the next line.
  // enqueue("https://cdn.polyfill.io/v2/polyfill.min.js?features=default,es6,fetch", [], true);

  $siteurl = get_site_url();
  \wp_localize_script("client-js", "theme", [
    "path" => str_replace($siteurl, "", get_stylesheet_directory_uri()),
    "assets" => [
      "stylesheet" => IS_WDS ? false : enqueue(asset_path("client.css")),
      "javascript" => enqueue(asset_path("client.js"), ["wplf-form-js"]),
      "polyfill" => asset_path("polyfill.js"), // loaded by client.js if necessary
    ],
    "siteurl" => $siteurl,
    "lang" => pll_current_language(),
  ]);
}

function admin_assets() {
  $siteurl = get_site_url();
  \wp_localize_script("admin-js", "theme", [
    "path" => str_replace($siteurl, "", get_stylesheet_directory_uri()),
    "assets" => [
      "stylesheet" => enqueue(asset_path("admin.css")),
      "javascript" => enqueue(asset_path("admin.js")),
      "polyfill" => asset_path("polyfill.js"), // loaded by admin.js if necessary
    ],
    "siteurl" => $siteurl,
    "lang" => pll_current_language(),
  ]);
}

function editor_assets() {
  $file = ADMIN_MANIFEST["editor.css"];
  add_editor_style("dist/$file");
}

\add_action("wp_enqueue_scripts", "\\Vincit\\Assets\\theme_assets");
\add_action("admin_enqueue_scripts", "\\Vincit\\Assets\\admin_assets");
\add_action("login_enqueue_scripts", "\\Vincit\\Assets\\admin_assets");

if (is_admin()) {
  editor_assets();
}

// Gravity Forms makes some absolutely mental decisions.
// Loading scripts in head? Not on my watch.
add_filter("gform_tabindex", "\\__return_false");
add_filter("gform_init_scripts_footer", "\\__return_true");

add_filter("gform_cdata_open", function () {
  return "document.addEventListener('DOMContentLoaded', function() { ";
});

add_filter("gform_cdata_close", function () {
  return "}, false);";
});
