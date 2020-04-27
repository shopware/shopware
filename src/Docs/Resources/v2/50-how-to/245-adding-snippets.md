[titleEn]: <>(Adding snippets)
[metaDescriptionEn]: <>(Learn here how to add and extend Shopwares snippet files for the administration and the storefront)
[hash]: <>(article:how_to_add_snippets)

## Overview

This HowTo will help you extend an existing language in both the administration and the storefront. We will also show
you how to set up a completely new language in Shopware 6.

## General snippet structure

We decided to save snippets as `.json` files, so structuring and finding snippets you want to change is very easy. 
However, when using pluralization and/or variables, there are minor differences to expect between snippets in the
administration and the storefront.

#### Where to put my snippets?

In theory, you are free to put your snippets anywhere, as long as you correctly load your `.json` files. 
Yet, we recommend mirroring Shopware's core structure and if you do so, your project's structure should look like this:

```
MyPlugin
└─ src
   ├─ Resources
   │  ├─ administration
   │  │  └─ src
   │  │     └─ module
   │  │        └─ my-module-name
   │  │           └─ snippet
   │  │              ├─ de-DE.json
   │  │              └─ en-GB.json
   │  ├─ config
   │  │  └─ services.xml
   │  └─ snippet
   │     ├─ de_DE
   │     │  ├─ SnippetFile_de_DE.php
   │     │  └─ messages.de-DE.json
   │     └─ en_GB
   │        ├─ SnippetFile_en_GB.php
   │        └─ messages.en-GB.json
   └─ MyPlugin.php
```

#### Administration

When adding new snippets to the administration, be aware that variables must be enclosed in curly brackets to enable the `$tc()`
method to find them. Pluralization on the other hand only requires a `|` to separate snippets that display information on
varying amounts. With two pluralized snippets, it's possible to distinguish between `one` and `more than one`. By adding a
third pluralized snippet, the first entity also allows a `zero count` snippet.

Example:
```json
{
    "swag-discount": {
        "detail": {
            "title": "Discounts detail",
            "discountAdded": "You added a discount of {value}"
        },
        "list": {
            "total": "No discount found | One discount found | {n} discounts found",
            "deleteTotal": "Do you really want to delete this item? | Do you really want to delete these {count} items?"
        }
    }
}
```

#### Storefront

There is no explicit syntax for variables in the storefront. It is nevertheless recommended to encompass them with `%`
symbols to be extra clear on what their purpose is. Pluralization works for any natural number. Just remember to explicitly
define the intervals' amounts and ranges for that snippet.

Example:
```json
{
    "swag-discount": {
        "productDetail": {
            "headLineText": "There are %count% discounts available for %product%:",
            "description": "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam ..."
        },
        "cart": {
            "itemCounter": "{1} 1 discount item | ]1,Inf[ %count% discount items",
        }
    }
}
```

## Extending Administration snippets

In order to provide custom snippets for your module, you will have to add a new property (`snippets`) to the `main.js` file
in your project. Next, associate all imported `.json` files (that include your newly assembled snippets) with their locale.
That's it! The `module factory` will do the rest for you, as long as the mentioned locale is registered. By default, `de-DE`
and `en-GB` are always available in the Shopware 6 Administration. And so are all locales that were added by language packs.
Language packs for that matter are available via the Shopware plugin store.

Here's a simple example: 

```js
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Module.register('custom-module', {
    type: 'plugin',
    // ...
    
    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    // ...
});
```

In case of just adding snippets without registering a module you can also feed the snippet objects directly to the locale service:

```
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);
```

## Extending Storefront snippets

#### SnippetFile

Injecting snippets to the storefront is not a big deal at all, but unlike the snippets used across the administration,
storefront snippets additionally require a class that extends the `SnippetFileInterface`. A suitable name would e.g. be
`SnippetFile_en_GB.php`. Having created that file, you will have to implement the following five methods:

- `getName`: Returns the name of the snippet file as a string. By referring to this name, you can later access the translations.
It is **required** to use `messages.en-GB`, if you provide a whole new language. By default, an extension should call its
Storefront extension `storefront.en-GB`. Otherwise a describing domain, like shopware's PayPal plugin using `paypal.en-GB`,
is also okay.
- `getPath`: Each SnippetFile class has to point to the `.json` file, that contains the actual translations. Return its
path here. We suggest using the name already chosen in `getName` for your file name.
- `getIso`: Return the ISO string of the supported locale here. This is important, because the `Translator` collects every
snippet file with this locale and merges them to generate the snippet catalogue used by the storefront. 
- `getAuthor`: Guess what, return your vendor name here. This can be used to distinguish your snippets from all the other
available ones. The Administration snippet set module offers a filter, so users are able to easily find plugin specific snippets.
- `isBase`: Return `true` here, if your plugin implements a whole new language, such as providing french snippets for the
whole Shopware 6. Don't forget to watch your `getName` method then! Most of the times, you're just adding your own snippets to an existent language, then `false` will be
your way to go.

Example:
```php
<?php declare(strict_types=1);

namespace MyPlugin\Resources\snippet\en_GB;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFile_en_GB implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'storefront.en-GB';
    }

    public function getPath(): string
    {
        return __DIR__ . '/storefront.en-GB.json';
    }

    public function getIso(): string
    {
        return 'en-GB';
    }

    public function getAuthor(): string
    {
        return 'Enter developer name here';
    }

    public function isBase(): bool
    {
        return false;
    }
}
```

#### Registering your service

Now it is time to register the SnippetFile in the DI container via the `services.xml` that came with the plugin.
If your plugin does not have a `services.xml` file yet, make sure to read [here](./../2-internals/4-plugins/010-plugin-quick-start.md#The services.xml).
This will help you understand the process of creation from the beginning on. Also notice there is a `shopware.snippet.file`
tag, which is essential in this process.

Example:
```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="MyPlugin\Resources\snippet\en_GB\SnippetFile_en_GB" public="true">
            <tag name="shopware.snippet.file"/>
        </service>
    </services>
</container>
```

## Providing a whole new language

In cases you would like to provide a whole new language with an unsupported language, you have be aware of some details.
The general file structure stays the same, but the import is a bit different. Notice: If you just want to add a dialect
or artificial languages like pirate speech, we recommend adding a new snippet set instead of a new language.

### Administration

New languages aren't registered at all, so that's your first job. Using the ```Shopware.Locale``` the
only thing you have to do is to register a basic snippet `.json` file using the `register` method. That
should look like this:

```js
import deAT from './snippet/de-AT.json';

Shopware.Locale.register('de-AT', deAT);
```

### Storefront

You won't find huge differences compared to extending storefront snippets. As mentioned in the SnippetFile paragraph, you
have to careful with two settings. With these:
- `getName()`: Your file **requires** to be named like `messages.<locale>` to enable symfony creating a new base catalogue.
- `getBase()`: This has to return `true` in order to be recognized as a whole new language.
