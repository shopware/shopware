[titleEn]: <>(Extend snippets using plugins)
[titleDe]: <>(Extend snippets using plugins)

Extending snippets is very simple in `Shopware`. You can add complete language packs or just provide translatable content
for your plugin using simple `json` files and `tagged services`.

## Overview
To provide your snippets you need a `SnippetFile.php` and your snippets in a `json` file. The exact
locations and names of these files are arbitrary, but we suggest the following structure:
```
└── plugins
    └── SwagSnippetExample
        └── Resources
            └── SnippetFiles
                ├── SnippetFile_Tyrolean_de_DE.php
                └── messages.tyrolean.de-DE.json
```

## SnippetFile.php
Your `SnippetFile.php` implements `Shopware\Core\Framework\Snippet\Files\SnippetFileInterface` to provide all the necessary data
for the system to detect your extension.

You can filter snippets by `author` to make it easier to distinguish your work from the work of others.

In Addition, please make sure that `isBase()` returns the correct value. If you provide a whole new language, you should
return `true`. Otherwise, in case you want to extend a language just to provide your plugin's snippets or adding a dialect
based on another language like Swiss German, Esperanto, Pirate English or similar, `false` will be your choice.

## Extending the services
To collect the snippet files, it is necessary to add the tag `shopware.snippet.file` to every given `SnippetFile` service
entry in your `xml` file(s).

```
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="SwagSnippetExample\Resources\SnippetFiles\SnippetFile_Tyrolean_de_DE">
            <tag name="shopware.snippet.file"/>
        </service>
    </services>
</container>
```

## Download
Here you can *Download Link Here* the full plugin to the example shown above.