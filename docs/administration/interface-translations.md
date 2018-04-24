# Interface translations
In the new Shopware administration we switched from an `*.ini` file / database based hybrid system to a JavaScript based
i18n system suited for [`vue.js`](https://vuejs.org/) called [`vue-i18n`](https://github.com/kazupon/vue-i18n). The new
system provides us with a bunch of useful features.

## Snippets file format
We're using `*.json` files for the snippets. The name of the file is a combination of the language and country code
following the specification [RF-4647](https://www.ietf.org/rfc/rfc4647.txt).

Example:
```
en-US       // language code e.g. "en" separated by a hyphens and followed by the country code e.g. "US"
de-DE
de-AT
```

The snippet files can be found in the directory called `src/app/snippets`. The file itself follows conventions which we
would like to highlight in the following:

**Syntax example:**
```
{
    "global": {
        "<default-component-name>": {
            "<type><function>": "<snippet>"
        }
    },
    "<module-name>": {
        "<page-view-or-component-name>": {
            "<type><function>": "<snippet>"
        }
    }
}
```

**Example:**
```
{
    "global": {
        "sw-pagination": {
            "labelItemsPerPage": "Items per page" 
        }
    },
    "sw-product": {
        "global": {
            "mainMenuItemList": "Products",
            "mainMenuItemAdd": "Add new product"
        },
        "list": {
            "buttonAddProduct": "Log in"
        }
    }
}
```

Global interface translations for default components are in the `global` property. Global module translations like menu
items can be found under the property `global` under the module namespace.

## Register new locales
We're providing an interface to third-party developers to register new locale:

```
Shopware.Locale.register('ca-ES', {
    'sw-login': {
        'index': {
            'buttonSave': 'Guardar'
        }
    }
    ...
});
```

## Extending existing locales

It is possible for third-party developers to extend existing locales with translations for their plugin / module:

```
Shopware.Locale.extend('en-UK', {
    'sw-login': {
        'index': {
            'buttonSave': 'Fancy save'
        }
    },
    'sw-awesome-module': {
        'index': {
            'buttonFancyUnicorn': 'Enable unicorn power'
        }
    }
});
```

## Further documentation
The full documentation for the Vue.js plugin can be found [here](https://kazupon.github.io/vue-i18n/en/). It provides
pluralization, html formatting, date time localization, number and currency formatting as well as fallback localization.