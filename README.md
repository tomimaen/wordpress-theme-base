# Vincit WordPress theme base
Bleeding edge starter theme.

## Features

### Theme features
- [x] Templates
- [x] Pagebuilder, powered by [ACF](https://www.advancedcustomfields.com/resources/flexible-content/)
- [x] Cleaner menus that are accessible
- [x] Automatic editor stylesheet
- [x] (Multilingual) options page support
- [x] Polylang support with fully controllable string translations from the admin
- [x] Automatic asset manifest handles asset cachebusting and delivers latest assets

### Developer features
- [x] Current environment is listed in window title
- [x] Accessible custom radiobuttons and checkboxes
- [x] Works with React (out-of-the-box, has demos)
- [x] Modern JavaScript support
  - [x] Built with Webpack 3
  - [x] **CSS Hot Module Replacement** (HMR)
  - [x] **JS Hot Module Replacement** (HMR)
  - [x] ES2017+ (stage-2)
  - [x] ESLint
  - [x] Sourcemaps
  - [x] Enforces case sensitive paths so build works on all platforms
- [x] CSS Preprocessor support
  - [×] Preconfigured with Stylus
- [x] PostCSS
  - [×] Autoprefixer
  - [x] Flexbug fixer
  - [] Any [PostCSS plugin](https://github.com/postcss/postcss#plugins) that you add (autogenerated RTL?)
- [x] Automatic image optimization using imagemin
- [x] PHPCS, based on PSR2.

## Screenshots
Nope.

[View the demo instead](https://wordpress.vincit.io).
## Requirements / dependencies
- PHP 7
- Composer
- Node 6 (preferably latest)
- npm 5

Optional:
- [aucor/wp_query-route-to-rest-api](https://github.com/aucor/wp_query-route-to-rest-api) for sample widgets
- [ACF](https://advancedcustomfields.com) for options page and pagebuilder support

The theme will fail with anything less than PHP 7, but making it PHP 5 compatible shouldn't be too hard, just fix the errors as they appear.

## Things to keep in mind
Webpack-dev-server will proxy your WordPress installation, and serve it through localhost:8080. WordPress doesn't know anything about this, and it will continue to output links with the original domain, so any forms or links do not work out of the box. The theme will enqueue a chunk of JS that will transform all links and forms that exist at DOMContentLoaded, so majority of links and forms will work. The adminbar is a prime example where links "do not work".

While the admin works (for the most part!) through wds, I wouldn't recommend using it. If you want to keep the admin bar visible while you develop the site, simply login twice. Once at https://wordpress.local/wp-admin, and once at https://localhost:8080/wp-admin.

**Why not?**

You *WILL* run into problems. Two examples from JavaScript console:
> Uncaught DOMException: Failed to execute 'replaceState' on 'History': A history state object with URL 'https://wordpress.local/wp-admin/post.php?post=439&action=edit' cannot be created in a document with origin 'https://localhost:8080' and URL 'https://localhost:8080/wp-admin/post.php?post=439&action=edit&message=6'.

> Access to Font at 'https://wordpress.local/wp-content/plugins/advanced-custom-fields-pro/assets/font/acf.woff?57601716' from origin 'https://localhost:8080' has been blocked by CORS policy: No 'Access-Control-Allow-Origin' header is present on the requested resource. Origin 'https://localhost:8080' is therefore not allowed access.

CORS isn't a problem normally, as we set `header("Access-Control-Allow-Origin: *");` to any request that's coming from wds,but the font in question isn't going through wds.

You're going to have a very bad time if you ignore this. If it's not a direct error, it's going to be a badly coded plugin that's going to cause it.

## Usage
Clone the theme or install it with the installer that ships with [Vincit/wordpress](https://github.com/Vincit/wordpress). Composer is also an option.
```sh
git clone git@github.com:Vincit/wordpress-theme-base.git themename

# OR (with first vagrant up if using Vincit/wordpress)
# Runs automatically. Answer yes to the question when prompted.

# OR (Vincit/wordpress installer)
./install # Follow the instructions

# OR (composer)
composer require vincit/wordpress-theme-base # append dev-master to get the latest version (potentially unstable)
```

Install dependencies:
```
npm install
```

### If you installed manually (and not with the installer)
Webpack requires some information from your setup. Mainly the URL of the site, and path to your theme. Open `package.json` and change `publicPath` and `proxyURL` to correct values.

Start watching for changes:
```
npm run watch # or npm run start, but webpack-dashboard is buggy at the moment
```

Find & replace at least these strings:
`wordpress-theme-base` => ???

`WordPress theme base` => ???

```
find . -not -path "./node_modules/*" -type f -name "*.*" -exec sed -i'' -e 's/wordpress-theme-base/your-desired-slug/g' {} +
find . -not -path "./node_modules/*" -type f -name "*.*" -exec sed -i'' -e 's/WordPress theme base/Your theme name/g' {} +
```

## Templating
Most people seem to start building themes using "the underscores" way:

```php
/* Start the Loop */
while ( have_posts() ) : the_post();

  /*
   * Include the Post-Format-specific template for the content.
   * If you want to override this in a child theme, then include a file
   * called content-___.php (where ___ is the Post Format name) and that will be used instead.
   */

  get_template_part( 'template-parts/content', get_post_format() );

endwhile;

the_posts_navigation();
```

This is problematic in a few ways. One is scoping. `get_template_part()` uses require under the hood,
but your variables are not in scope if you try to do this:

```php
$variable = "string";

get_template_part("variableOutOfScope");
```

`$variable` is simply `null` inside variableOutOfScope. Things are different if you were to use require directly:

```php
$variable = "string";

require "variableInScope.php"; // try echoing $variable!
```

But using `require` is frowned upon. The underscore way advices you to define your variables as globals.

Globals are a code smell, and you should avoid them whenever possible.

### Meet functions as templates
By using namespaced functions as templates, we can avoid using globals, for the most part.

A function takes parameters, and returns a value. You can easily have default parameters and automatically override all of them should you want to.

Now that that's said, our template functions can't really return. Or they can, but the only use for returning is to stop the execution of the function, and thus to prevent the template from displaying at all. The template rendering itself is a side effect. When used through an output buffer (more on that later) the return value is discarded. But that's not a problem, templates shouldn't return a value. A function shouldn't do more than one thing, and our template functions thing is to print the template

In this theme, templates live in the `inc/templates` folder, and have the following structure:

```php
<?php
namespace Vincit\Template;

// use \Vincit\Media; // Optional use declarations, get image helpers or similar

/**
 * Is actually a link, but looks like a button.
 *
 * @param mixed $data
 */
function Button($data = []) {
  $data = params([
    "text" => null,
    "link" => null,
    "color" => [
      "value" => "white",
    ],
  ], $data);

  if (empty($data["text"]) || empty($data["link"])) {
    // Default is null, but ACF may populate this with empty string
    return false;
  } ?>

  <a <?=className("button", "bg--{$data[color][value]}")?> href="<?=$data["link"]?>">
    <?=$data["text"]?>
  </a><?php
}
```

Let's break it down. On the first line, there's a namespace declaration. This is important.

On the parameter definition, we're making the function receive one parameter, the default being empty array. We're then using that parameter to override our default parameters.

Then, we're checking that the button has a text, and a link, or return early, and do not print the button. We're then closing the PHP tag, in order to write plain HTML. As you can see, the HTML is not part of a return statement, but a side effect.

Unfortunately, JSX isn't a thing in PHP, so we're forced to resort into side effects. Whenever you call `Button`, everything that's not inside PHP tags is immediately outputted, so you can't save the button to a variable:

```
// Inside another template, under namespace \Vincit\Template

$button = Button(["text" => "Click me!", "link" => "#"]);

echo get_the_content();
echo $button; // Button is actually before the content!
```

This may, or may not be an actual problem, depending on your use case. One solution is to use output buffering in order to capture the output. The built-in pagebuilder class does that with the `block()` method.


```php
$builder = \Vincit\Pagebuilder::instance();
$button = $builder->block("Button", ["text" => "Click me!", "link" => "#"]);
// Equivalent to calling Button() manually, but with output buffer and error is catchable.

echo get_the_content();
echo $button; // Button is after the content!
```

When the current namespace is Vincit\Template, you can and probably should call the template functions directly, instead of going through the pagebuilder class. When working with files such as `singular.php`, use the class.

```php
$builder = \Vincit\Pagebuilder::instance();

while (have_posts()) { the_post();
  echo $builder->block("SinglePost", [
    "title" => get_the_title(),
    "content" => get_the_content(),
    "image" => get_post_thumbnail_id(),
  ]);

  echo $builder->block("CommentList", [
    "post_id" => get_the_ID(),
  ]);
}
```

It's possible to build highly dynamic but maintainable components this way. You can create a generic [PostList](https://github.com/Vincit/wordpress-theme-base/blob/master/inc/templates/PostList.php) component that uses the main query by default, but switches to a custom query if it's supplied and even supply a different (template) function.

Try it out by modifying the PostList call on `index.php`.

```php
echo $builder->block("PostList", ["template" => "print_r"]);
```

It's the same magic that's behind actions and filters.

## ACF
The theme works without ACF, and it's not required. However, ACF is a great way to make your templates editable from the admin.

There's a certain way you want to do things in order to keep things simple.

When creating fields for a template / component, create it as a new **disabled** field group. You'll probably want to use the Group field to enclose your fields under a single key, and to make styling the admin easier. There are some exceptions, but in most cases, stick with the Group field.

Let's say you have a special front page template, and a pagebuilder.

You create a new field group for your front page, or use the existing one, and use the Clone (seamless) field to clone the newly created field group. Most likely you do not have to use a prefix, you can just leave the field name and key empty.

The pagebuilder field group is a flexible content field. Simply add a new "layout", enter the layout label and name. Pay extra attention to the name field, ACF wants to lowercase the value of label, but you **need** to make sure the field value matches your template function name, such as Hero.


## FAQ
### What's with the folder structure?
- build/ contains build related things, such as Webpack config.
- dist/ contains the build itself. Never committed to version control.
- inc/ contains server side includes. Basically if you would put it in functions.php, put it here.
- src/ contains client side source files, including JavaScript, Stylus and images.
  - js/ contains JavaScript files.
  - styl/ contains Stylus files.
  - img/ contains images, including SVGs.
  - Files inside src/ directly will be used to build files: `client.styl` => `client.css` and so on.

### Why are the styles flashing when I'm first loading the page?
Styles are bundled in the JavaScript bundle, and applying them takes a moment. This is how Webpack works.
When using the production build, styles are extracted from the bundle and loaded as normal.

### WTF, why are you importing a `.styl` file inside JavaScript?
To bundle the styles to the JS bundle.

### I installed all the dependencies and ran npm run watch, but when I try to access http://localhost:8080 I get the following error: Error occured while trying to proxy to: localhost:8080/
You don't have WordPress installed at https://wordpress.local, which is the default address. Change the proxyURL value in package.json and try again.

### You promised us HMR, but it doesn't work?!!
See above. HMR requires publicPath value to work. This theme defaults to http://localhost:8080/wp-content/themes/wordpress-theme-base/dist/, if you installed the theme in a directory with another name you obviously need to change it.
Change the value in package.json.

### I did the above but HMR still doesn't work?
Git gud. HMR requires you to write your code accordingly, example:
- [Module](https://github.com/Vincit/wordpress-theme-base/blob/master/src/js/components/SampleWidgets/Clock.js)
- [index.js](https://github.com/Vincit/wordpress-theme-base/blob/master/src/js/components/SampleWidgets/index.js)

If using React, you should be set.

Consult Webpack documentation if necessary.

### I get a white screen or Fatal error: Uncaught Exception: Enqueued file must be a css or js file
Build the theme after installing it. Run `npm install`.

### How to use webfonts?
Place the font files in src/fonts. The loaders working directory is src, even if you use @font-face in styl/typography.styl.

```css
@font-face {
  font-family: 'FontName'
  src: url('./fonts/Font.eot')
  src: local('.'), local('.'),
    url('./fonts/Font.eot?#iefix') format('embedded-opentype'),
    url('./fonts/Font.woff') format('woff'),
    url('./fonts/Font.ttf') format('truetype')
  font-weight: normal
  font-style: normal
}
```

### I tried to use an svg background image but it doesn't quite work
[svg-inline-loader](https://github.com/webpack-contrib/svg-inline-loader) purifies SVGs and inlines them, so `background: url('./img/svg/background.svg')` results in `background: url(<svg>..</svg>)`.

The solution is to put the SVG in src/img/no-inline/svg directory: `background: url('./img/no-inline/svg/close.svg')` => `background: url(data:image/svg+xml;base64...)`


## I got "TypeError: Cannot read property 'split' of null" when starting the watcher
Most likely the proxyURL in package.json is wrong. Make sure to include protocol: `https://wordpress.local`

## I've started the watcher, but I have no styles and JavaScript is broken? This started on it's own.
It's possible that your browser has stopped trusting the certificate (happens surprisingly often, at least on Chrome on Linux).

Scripts and styles are loaded from https://wordpress.local, even though the development server runs on https://localhost:8080. If your system doesn't trust self-signed certificates automatically, you might have to navigate to https://wordpress.local, and add an exception or confirm that you want to use the site, regardless of the "dangerous" cert.

## I don't care for React, how do I get rid of it?
If you don't use React, you might want to remove it. This isn't necessary, as your build doesn't include React if it isn't used. Simply commenting out the line that imports sample widgets in client.js should remove React from the bundle.

If you still want to remove React entirely, remove this from build/webpack.parts.js transpileJavaScript method: `require('react-hot-loader/babel')`. Then remove `'react-hot-loader/patch'` from the entries object in webpack.client.js, and delete any React packages from package.json.
