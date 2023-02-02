[titleEn]: <>(Plugin meta information)
[hash]: <>(article:plugin_meta)

To provide Shopware and the shop owner with information about your plugin, you have to supply a `composer.json` with your plugin.
Some information are gathered through the default properties of a `composer.json`.
For some Shopware specific information, you have to extend the `extra` property.
Have a look at the [composer schema](https://getcomposer.org/doc/04-schema.md), to learn more about the properties.

## Example `composer.json`

```json
{
    "name": "swag/example-plugin",
    "description": "Example plugin",
    "version": "v1.0.1",
    "type": "shopware-platform-plugin",
    "license": "MIT",
    "authors": [
        {
            "name": "Example Company",
            "homepage": "https://my.example.com",
            "role": "Manufacturer"
        }
    ],
    "require": {
        "shopware/core": "6.1.*",
        "example-plugin": "1.0.0"
    },
    "extra": {
        "shopware-plugin-class": "Swag\\ExamplePlugin\\ExamplePlugin",
        "plugin-icon": "src/Resources/config/plugin.png",
        "copyright": "(c) by shopware AG",
        "label": {
            "de-DE": "Beispiel Plugin für Shopware",
            "en-GB": "Example plugin for Shopware"
        },
        "description": {
            "de-DE": "Deutsche Beschreibung des Plugins",
            "en-GB": "English Description of Plugin"
        },
        "manufacturerLink": {
            "de-DE": "https://store.shopware.com/shopware-ag.html",
            "en-GB": "https://store.shopware.com/en/shopware-ag.html"
        },
        "supportLink": {
            "de-DE": "https://docs.shopware.com/de",
            "en-GB": "https://docs.shopware.com/en"
        }
    },
    "autoload": {
        "psr-4": {
            "Swag\\BaseClass\\": "src/"
        }
    }
}
```

## Explanation of the properties

|             property            |                                                             description                                                          |
|---------------------------------|----------------------------------------------------------------------------------------------------------------------------------|
| name                            | Name of your package                                                                                                             |
| description                     | The composer JSON schema requires a short description of your package. This field does not allow HTML.                                                            |
| version                         | Current version of your plugin                                                                                                   |
| type                            | Set the type to `shopware-platform-plugin`. Otherwise Shopware won't be able to recognize your plugin                            |
| license                         | Provide the license model of your plugin, e.g. `MIT` or `proprietary`                                                            |
| authors                         | Collection of the authors of your plugin. If one or more authors with the role `Manufacturer` are provided, only these will be written to the database.|
| require                         | Add your dependencies here. This should be at least `shopware/core`                                                              |
| extra                           | The `extra` property is used to provide some Shopware specific information                                                       |
| extra - shopware-plugin-class   | The fully qualified class name of your plugin's base class                                                                       |
| extra - plugin-icon             | The path to the plugin's icon file. This is optional if you don't have any custom plugin icon                                    |
| extra - copyright               | Set a copyright for your plugin                                                                                                  |
| extra - label                   | The name of your plugin which is displayed to the Shopware user. [Translatable](./050-plugin-information.md#translations)        |
| extra - description             | The description of your plugin which is displayed to the Shopware user. This field does not allow HTML. [Translatable](./050-plugin-information.md#translations) |
| extra - manufacturerLink        | Link to your homepage. [Translatable](./050-plugin-information.md#translations)                                                  |
| extra - supportLink             | A link to your support homepage. [Translatable](./050-plugin-information.md#translations)                                        |
| autoload                        | Required to have a custom [PSR-4 autoloader](https://getcomposer.org/doc/04-schema.md#psr-4) for your custom plugin directory |

## Requirements

The `require` field in the composer.json file defines on which dependencies your plugin relies.
As you want to develop a plugin for Shopware, you should have at least an entry for `shopware/core` here.
If you also rely on other parts of Shopware 6 you should als require them, e.g. `shopware/storefront`.
In addition to that, it is also possible to define dependencies on other plugins or even every other composer package.

## Translations

Some fields of the plugin entity are translatable.
Shopware will try to match each of your provided translations with an existing language in the system.
For more information about the translation system in Shopware have look [here](./../10-core/130-dal.md).
Your translation locale code must look like that "de-DE", "en-GB", "de-CH", etc.
If a language with this locale is not available, the translation will not be written.

## Changelog

The changelog for your plugin now has to be located in another file.
Have as look at our [changelog guide](./060-plugin-changelog.md) to figure out how that's done in Shopware 6.

## Icon

A plugin can have an icon which will be rendered in the shopware administration. The default path for an icon is `src/Resources/config/plugin.png` relative from the plugin root folder. This path can be overridden using the `extra` object in the `composer.json` as follows:
```json
{
    "extra": {
        "plugin-icon": "myFolder/icon.png"
    }
}
```
The icon should be a png file with the size 40 x 40 px. Since it is stored in the database as `mediumblob` the theoretical max file size is 16 MB.
