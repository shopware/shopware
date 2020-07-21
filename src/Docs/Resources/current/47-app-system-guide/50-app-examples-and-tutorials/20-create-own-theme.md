[titleEn]: <>(Writing your own theme)
[metaDescriptionEn]: <>(This tutorial helps you with creation your own theme as app.)
[hash]: <>(article:app_write_theme)

## Example theme

Inside the "Resources" folder you'll need a `theme.json` file which is like the manifest file but specific for theme
purposes. This file controls which resources the theme should load and which core templates it should use.

```
...
└── DemoTheme
    ├── Resources
    │   └── theme.json <--
    └── manifest.xml
```

A minimal `theme.json` for the "DemoTheme" looks like this:

```json
{
  "name": "DemoTheme",
  "author": "Shopware AG",
  "views": [
     "@Storefront",
     "@Plugins",
     "@DemoTheme"
  ]
}
```

In general the usage of the `theme.json` file is the same as for themes which are published through a regular plugin.
In contrast to a regular plugin the `theme.json` has to be directly inside the "Resources" folder: `DemoTheme/Resources/theme.json`.

You can find out more about the theme configuration options in the [theme guide](./../../30-theme-guide/20-configuration.md).

### Activate the theme

Once your `theme.json` is set up you already have a valid theme. The theme can now be activated already 
- even though the theme hasn't done any modifications yet. This can be done in the administration inside the "Themes" 
section of the main menu or with the `thmeme:change` command.

```bash
# run this to change the current storefront theme
$ bin/console theme:change
	
# you will get an interactive prompt to change the 
# current theme of the storefront like this
	
Please select a sales channel:
[0] Storefront | 64bbbe810d824c339a6c191779b2c205
[1] Headless | 98432def39fc4624b33213a56b8c944d
> 0

Please select a theme:
[0] Storefront
[1] DemoTheme
> 1

Set "DemoTheme" as new theme for sales channel "Storefront"
Compiling theme 13e0a4a46af547479b1347617926995b for sales channel MyTheme	
```

### Templates

With the theme system it is possible to adjust or override Shopware core templates. The Shopware templates are using 
the [Twig](https://twig.symfony.com/) template engine. The template engine provides various functionalities like 
logical operations e.g. if-statements or for-loops. Most importantly the template engine comes with a block system 
which can define logical sections in the HTML markup. Those blocks are wrapped around the most important parts of 
the core templates and can be modified by a theme.

The Shopware storefront templates can be found in `views` directory of the Storefront bundle: 

```
platform/src/Storefront/Resources/views/storefront
```

In order to modify the templates you'll need a `views` directory in your theme app as well. The directory structure
is similar to the structure of the core storefront.

```
...
└── DemoTheme
    ├── Resources
    │   ├── views 
    │   │   └── storefront <-- Your Twig templates go here
    │   └── theme.json
    └── manifest.xml
```

Every `*.html.twig` file which is placed under the same name and directory structure like the Shopware core file will
be overwritten by your theme by default. To illustrate this we create a `logo.html.twig` file just like inside the core
views directory:

```
...
└── DemoTheme
    ├── Resources
    │   ├── views 
    │   │   └── storefront 
    │   │       └── layout
    │   │           └── header
    │   │               └── logo.html.twig <-- Override core logo template
    │   └── theme.json
    └── manifest.xml
```
When taking a look at our theme the whole logo template should be overwritten with empty content. In order to modify
specific blocks rather than overwriting an entire template you can use the `{% sw_extends %}` function:

```twig
{% sw_extends '@Storefront/storefront/layout/header/logo.html.twig' %}

{# Use the 'layout_header_logo_image' block and append a sub headline to the logo  #}
{% block layout_header_logo_image %}

    {# The 'parent()' call gets the original content of the block an can put it at a desired place. #}
    {{ parent() }}
    <h3>Saas app theme</h3>
{% endblock %}
```

Learn more about templates in the [twig section of the theme guide](./../../30-theme-guide/30-twig-templates.md).

### SCSS

In order to provide custom styling for your theme you can add [SCSS](https://sass-lang.com/) files inside the 
`Resources/app` folder of your theme. The "app" folder represents the JavaScript/frontend application part of 
your theme where you can find all JavaScript and SCSS files.

```
...
└── DemoTheme
    ├── Resources
    │   ├── app
    │   │   └── storefront
    │   │       └── src
    │   │           └── scss
    │   │               └── base.scss <-- SCSS entry point file
    │   ├── views
    │   └── theme.json
    └── manifest.xml
```

Your `theme.json` needs to know about the file inside the "style" section in order to load the SCSS:

```json
{
  ...

  "style": [
    "@Storefront",
    "app/storefront/src/scss/base.scss" <-- SCSS entry point
  ],

  ...
}
```

Now you are able to add SCSS code to the `base.scss`. It is a very common pattern to use one entry point and import
[SCSS partials](https://sass-lang.com/guide) from there.

```scss
// DemoTheme/Resources/app/storefront/src/scss/base.scss

body {
  background-color: $gray-300;
}
```

Shopware comes with an integrated compiler which will transpile the SCSS back to CSS. You can compile your theme
with the `theme:compile` command:

```bash
# run this to re-compile the SCSS of the current storefront theme
$ bin/console theme:compile
```

Learn more about SCSS in the [SCSS section of the theme guide](./../../30-theme-guide/50-scss.md).

### JavaScript

You can add custom JavaScript to your theme by creating a `main.js` file which is the default entry-point for JavaScript:

```
...
└── DemoTheme
    ├── Resources
    │   ├── app
    │   │   └── storefront
    │   │       └── src
    │   │           └── main.js <-- JavaScript entry point file
    │   ├── views
    │   └── theme.json
    └── manifest.xml
```

Now you are able to add JavaScript code to your `main.js` file:

```js
// DemoTheme/Resources/app/storefront/src/main.js

console.log('DemoTheme JavaScript loaded');
```

In contrast to SCSS you add the minified version of your JavaScript to your `theme.json` instead of the `main.js`:

```json
{
  ...

  "script": [
    "@Storefront",
    "app/storefront/dist/storefront/js/demo-theme.js" <-- Compiled JavaScript
  ],

  ...
}
```

You can compile the JavaScript with the `./psh.phar storefront:build` command. The minified/compiled JavaScript can
be found inside the `app/storefront/dist/storefront/js/` directory.

Find out more about JavaScript in the [JavaScript section of the theme guide](./../../30-theme-guide/60-javascript.md).

### Snippets

**Note that this feature is only available from v6.2.3 onward**.
In order to include custom storefront snippets in your app, simply make use of the autoloading snippet feature.
Therefore place your snippet files under a snippet folder in your Resources folder:

```
...
└── DemoTheme
    ├── Resources
    │   ├── app
    │   ├── views
    │   ├── snippet
    │   │   ├── storefront.de-DE.json <-- snippets with german translations
    │   │   └── storefront.en-GB.json <-- snippets with english translations
    │   └── theme.json
    └── manifest.xml
```

For a detailed explanation on how the snippet files are loaded, take a look into the according section of the [Theme Guide](./../../30-theme-guide/40-snippets.md).
