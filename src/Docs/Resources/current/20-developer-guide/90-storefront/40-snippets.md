[titleEn]: <>(Snippets and Translations)
[hash]: <>(article:developer_storefront_snippets)

To extend a language in Shopware 6 you can add your own snippets in your plugin.

## General snippet structure

We have decided to save snippets as `.json` files, so it is very easy to structure and find snippets you want to change. 
However, when using pluralization and/or variables, you can expect slight differences between snippets 
in the administration and the storefront.

## Snippet location

In theory, you are free to place your snippets anywhere as long as you load your .json files correctly. 
However, we recommend that you mirror the core structure of Shopware. If you choose to do that, 
the structure of your project should look like this:

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
   │  └─ snippet
   │     ├─ de_DE
   │     │  ├─ SnippetFile_de_DE.php
   │     │  └─ messages.de-DE.json
   │     └─ en_GB
   │        ├─ SnippetFile_en_GB.php
   │        └─ messages.en-GB.json
   └─ MyPlugin.php
```

There is no explicit syntax for variables in the storefront. It is nevertheless recommended to encompass them 
with % symbols to be extra clear on what their purpose is. Pluralization works for any natural number. 
Just remember to explicitly define the intervals' amounts and ranges for that snippet.

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

## Extending Storefront snippets

In this guide, we will give you an overview on how to extend Storefront snippets. In case you need further assistance,
we got you covered. In our HowTo section, we provide a detailed tutorial on 
[extending Storefront snippets](./../../50-how-to/245-adding-snippets.md).

### SnippetFile

Unlike the snippets used across the administration, Storefront snippets require a class that extends the 
`SnippetFileInterface`. A suitable name would e.g. be `SnippetFile_en_GB.php`. Having created that file, 
you will have to implement the following five methods:

- `getName`: Returns the name of the snippet file as a string. 
- `getPath`: Return its path here. We suggest using the name already chosen in `getName` for your file name.
- `getIso`: Return the ISO string of the supported locale here.  
- `getAuthor`: Return your vendor name here.
- `isBase`: Return `true` here, if your plugin implements a whole new language.

### Registering your service

Now it is time to register the SnippetFile in the DI container via the `services.xml` that came with the plugin.
If your plugin does not have a `services.xml` file yet, make sure to read 
[here](./../40-services-subscriber.md). This will help you understand the 
process of creation from the beginning on. Also notice there is a `shopware.snippet.file` tag, 
which is essential in this process.
