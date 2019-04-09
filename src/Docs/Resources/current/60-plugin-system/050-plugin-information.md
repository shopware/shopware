[titleEn]: <>(Plugin meta information)
[titleDe]: <>(Plugin meta information)
[wikiUrl]: <>(../plugin-system/plugin-meta-information?category=shopware-platform-en/plugin-system)

To provide Shopware and the shop owner with information about your plugin, you have to supply a `composer.json` with your plugin.
Some information are gathered through the default properties of a `composer.json`.
For some Shopware specific information, you have to extend the `extra` property.
Have a look at the [composer schema](https://getcomposer.org/doc/04-schema.md), to learn more about the properties.

## Example `composer.json`
```json
{
    "name": "swag/paypal",
    "description": "All PayPal payment methods in one plugin",
    "version": "v1.0.1",
    "type": "shopware-platform-plugin",
    "license": "MIT",
    "authors": [
        {
            "name": "shopware AG"
        }
    ],
    "require": {
        "shopware/platform": "1.1.1"
    },
    "extra": {
        "shopware-plugin-class": "SwagPayPal\\SwagPayPal",
        "copyright": "(c) by shopware AG",
        "label": {
            "de_DE": "PayPal Produkte für Shopware",
            "en_GB": "PayPal Products for Shopware"
        },
        "description": {
            "de_DE": "Deutsche Beschreibung des Plugins",
            "en_GB": "English Description of Plugin"
        },
        "manufacturerLink": {
            "de_DE": "https://store.shopware.com/shopware-ag.html",
            "en_GB": "https://store.shopware.com/en/shopware-ag.html"
        },
        "supportLink": {
            "de_DE": "https://docs.shopware.com/de",
            "en_GB": "https://docs.shopware.com/en"
        }
    }
}
```

## Explanation of the properties

| property | description |
|----------|-------------|
| name | Name of your package |
| description | The composer JSON schema requires a short description of your package |
| version | Current version of your plugin |
| type | Set the type to `shopware-platform-plugin`. Otherwise Shopware won't be able to recognize your plugin |
| license | Provide the license model of your plugin, e.g. `MIT` or `proprietary` |
| authors | Collection of the authors of your plugin |
| require | Add your dependencies here. This should be `shopware/platform`, but could also be another plugin or composer package |
| extra | The `extra` property is used to provide some Shopware specific information |
| extra - shopware-plugin-class | The technical name of your plugin. Must be unique and should contain your vendor prefix |
| extra - copyright | Set a copyright for your plugin |
| extra - label | The name of your plugin which is displayed to the Shopware user. [Translatable](#translations) |
| extra - description | The description of your plugin which is displayed to the Shopware user. [Translatable](#translations) |
| extra - manufacturerLink | Link to your homepage. [Translatable](#translations) |
| extra - supportLink | A link to your support homepage. [Translatable](#translations) |

## Translations
Some fields of the plugin entity are translatable.
Shopware will try to match each of your provided translations with an existing language in the system.
For more information about the translation system in Shopware have look [here](../20-data-abstraction-layer/9-translations.md).
Your translation locale code must look like that "de_DE", "en_GB", "de_CH", etc.
If a language with this locale is not available, the translation will not be written.
