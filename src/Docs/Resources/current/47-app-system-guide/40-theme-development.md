[titleEn]: <>(Theme base in apps)
[metaDescriptionEn]: <>(This guide deals with theme development in apps)

Themes are - as said [before](./10-plugins-and-apps.md) - ready-made designs that primarily redesign your storefront 
and do not provide major functional enhancements. Themes are neither apps nor plugins, but can be delivered with apps
or plugins. Both via app and plugin, a theme looks exactly the same - usually no changes are necessary.

The app system was designed to make it easy to migrate your existing themes to the app system.
It's based on the current theme system, that way you can reuse most of your existing themes code.

## Installation

As we ship themes as apps, the [installation](./30-app-base-guide.md) itself is the same as with usual apps.

So once you have installed your theme via `bin/console app:refresh`, your theme should show up in the theme manager, 
and you should be able to use the usual theme commands, 
like `bin/console theme:compile` or `bin/console theme:refresh` with your theme.

## Manifest file

As the app system does not depend on the plugin system you don't need to provide a composer.json and plugin base class. 
Instead, you have to provide the metadata of your theme in a `manifest.xml` file in your themes root folder, as usual in 
apps.

A minimal manifest can be found in the getting started section. The modifications you want to make in your theme 
have to be stored in the `Resources` folder, just like in the current plugin theme system:
```
...
└── DemoTheme
      └── Resources
      └── manifest.xml
```

Please note that it is absolutely possible to provide a `manifest.xml` and a `composer.json` and plugin base class 
in one theme, that way your theme is compatible with both the plugin system and the app system.

## Limitations

If you use the app system to publish your theme, there are some limitations to keep in mind:

* You can't extend the Shopware php backend, all php files you may include in your theme won't be executed. Currently,
this leads to the limitation that it is not possible to add custom snippets to your theme.
* You can't extend the Shopware adminstration, all js files provided in the `administration` namespace will be ignored.

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

A basic `theme.json` for the "DemoTheme" looks like this:

```json
{
  "name": "DemoTheme",
  "author": "Shopware AG",
  "views": [
     "@Storefront",
     "@Plugins",
     "@DemoTheme"
  ],
  "style": [
    "app/storefront/src/scss/overrides.scss",
    "@Storefront",
    "app/storefront/src/scss/base.scss"
  ],
  "script": [
    "@Storefront",
    "app/storefront/dist/storefront/js/demo-theme.js"
  ],
  "asset": [
    "app/storefront/src/assets"
  ]
}
```

In general the usage of the `theme.json` file is the same as for themes which are published through a regular plugin. 
You can find out more about the theme configuration options in the [theme guide](./../30-theme-guide/20-configuration.md).

### Activate the theme

Once your `theme.json` is set up you already have a valid theme. The theme can now be activated already - even though the
theme hasn't done any modifications yet. This can be done in the administration inside the "Themes" section of the 
main menu or with the `thmeme:change` command.

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

With the theme system it is possible to adjust or override shopware core templates. The shopware templates are using the
[Twig](https://twig.symfony.com/) template engine. The template engine provides various functionalities like logical operations e.g. if-statements or for-loops. 
Most importantly the template engine comes with a block system which can define logical sections in the HTML markup.
Those blocks are wrapped around the most important parts of the core templates and can be modified by a theme.

The shopware storefront templates can be found in "views" directory of the Storefront bundle: 

```
platform/src/Storefront/Resources/views/storefront
```

In order to modify the templates you'll need a "views" directory in your theme app as well. The directory structure
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

Every `*.html.twig` file which is placed under the same name and directory structure like the shopware core file will
be overwritten by your theme by default. To illustrate this we create a `logo.html.twig` file just like inside the core
views direcotry:

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

Learn more about templates in the [twig section of the theme guide](./../30-theme-guide/30-twig-templates.md).

### SCSS

In order to provide custom styling for your theme you can add [SCSS](https://sass-lang.com/) files inside the `Resources/app` folder of your theme.
The "app" folder represents the JavaScript/frontend application part of your theme where you can find all JavaScript and SCSS files.

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

Learn more about SCSS in the [SCSS section of the theme guide](./../30-theme-guide/50-scss.md).
