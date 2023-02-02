[titleEn]: <>(Theme base in apps)
[metaDescriptionEn]: <>(This guide deals with theme development in apps)
[hash]: <>(article:app_theme-development)

Themes are - as said [before](./10-plugins-and-apps.md) - ready-made designs that primarily redesign your storefront 
and do not provide major functional enhancements. Themes are neither apps nor plugins, but can be delivered with apps
or plugins. Both via app and plugin, a theme looks exactly the same - usually no changes are necessary.

The app system was designed to make it easy to migrate your existing themes to the app system.
It's based on the current theme system, that way you can reuse most of your existing themes code.

## Installation and first steps

As we ship themes as apps, the [installation](./30-app-base-guide.md) itself is the same as with usual apps.

So once you have installed your theme via `bin/console app:refresh`, your theme should show up in the theme manager, 
and you should be able to use the usual theme commands, 
like `bin/console theme:compile` or `bin/console theme:refresh` with your theme.

As the app system does not depend on the plugin system you don't need to provide a composer.json and plugin base class. 
Instead, you have to provide the metadata of your theme in a `manifest.xml` file in your themes root folder, 
as usual in apps.

A minimal manifest can be found in the [base app guide](./30-app-base-guide.md). The modifications you want to make in 
your theme have to be stored in the `Resources` folder, just like in the current plugin theme system:
```
...
└── DemoTheme
      └── Resources
      └── manifest.xml
```

## Migrating existing themes

If you already created a Shopware 6 theme via Plugin, it is very simple to migrate it to the app system. So don't worry -
you don't need to do all work twice. There are a few limitations you can find in the penultimate paragraph you need to 
consider - but that's all you need to keep in mind. 

Please note that it is absolutely possible to provide a `manifest.xml` and a `composer.json` and plugin base class 
in one theme, that way your theme is compatible with both the plugin system and the app system.

## Limitations

If you use the app system to publish your theme, there are some limitations to keep in mind:

* You can't extend the Shopware php backend, all php files you may include in your theme won't be executed. Because of this
you need to use the [autoloading feature](./../30-theme-guide/40-snippets.md) of snippets to include storefront snippets in your theme.
**Note that this feature is only available from v6.2.3 onward**.
* You can't extend the Shopware administration, all js files provided in the `administration` namespace will be ignored.
Instead, you can achieve that in another way: You are able to add your own module, custom fields or buttons via 
manifest file - see our [base app guide](./30-app-base-guide.md) for details.

## Example

If you want to see how this guide is put into practice, we wrote a 
[tutorial to write a small template](./50-app-examples-and-tutorials/20-create-own-theme.md).
