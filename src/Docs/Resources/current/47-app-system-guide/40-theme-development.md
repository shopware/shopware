[titleEn]: <>(Theme base in apps)
[metaDescriptionEn]: <>(This guide deals with theme development in apps)

Themes are - as said [before]() - ready-made designs that primarily redesign your storefront and do not provide 
major functional enhancements. Themes are neither apps nor plugins, but can be delivered with apps or plugins. 
Both via app and plugin, a theme looks exactly the same - usually no changes are necessary.

The app system was designed to make it easy to migrate your existing themes to the app system.
It's based on the current theme system, that way you can reuse most of your existing themes code.

## Installation

As we ship themes as apps, the [installation]() itself is the same as with usual apps.

So once you have installed your theme via `bin/console app:refresh`, your theme should show up in the theme manager 
and you should be able to use the usual theme commands, 
like `bin/console theme:compile` or `bin/console theme:refresh` with your theme.

## Manifest file

As the app system does not depend on the plugin system you don't need to provide a composer.json and plugin base class. 
Instead you have to provide the metadata of your theme in a `manifest.xml` file in your themes root folder, as usual in 
apps.

A minimal manifest can be found in the getting started section. The modifications you want to make in your theme 
have to be stored in the `Resources` folder, just like in the current plugin theme system:
```bash
...
└── MyExampleApp
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

We wrote a little tutorial on how to white a theme as app. Please take a look at [how to provide a theme in your app]().
