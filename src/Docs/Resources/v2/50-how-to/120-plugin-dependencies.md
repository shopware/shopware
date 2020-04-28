[titleEn]: <>(Depending on other plugins)
[metaDescriptionEn]: <>(New in Shopware 6 is the possibility to properly require on other plugins to be in the system. This HowTo will cover that subject.)
[hash]: <>(article:how_to_plugin_dependency)

## Overview

New in Shopware 6 is the possibility to properly require on other plugins to be in the system.
This is done using the `require` feature from composer.
Further information about this can be found in the [official composer documentation](https://getcomposer.org/doc/04-schema.md#package-links).

## Setup

Each plugin for Shopware 6 has to own a `composer.json` file for it to be a valid plugin.
Creating a plugin is not explained here, make sure to read our [developer guide](./../20-developer-guide/10-plugin-base.md) first.

Since every plugin has to own a `composer.json` file, you can simply refer to this plugin by its technical name and its version
mentioned in the respective plugin's `composer.json`.

So, those are example lines of the `Plugin quick start` plugin's `composer.json`:
```
{
    "name": "swag/plugin-quick-start",
    "description": "Plugin quick start plugin",
    "version": "v1.0.0",
    ...
}
```

Important to note is the `name` as well as the `version` mentioned here, the rest of the file is not important for this
case here. You're going to need those two information to require them in your own plugin.

In order to require the `Plugin quick start` plugin now, you simply have to add these two information to your
own `composer.json` as a key value pair:

```json
{
    "name": "swag/plugin-dependency",
    "description": "Plugin requiring other plugins",
    "version": "v1.0.0",
    "type": "shopware-platform-plugin",
    "license": "MIT",
    "authors": [
        {
            "name": "shopware AG",
            "role": "Manufacturer"
        }
    ],
    "require": {
        "shopware/core": "6.1.*",
        "swag/plugin-quick-start": "v1.0.0"
    },
    "extra": {
        "shopware-plugin-class": "Swag\\PluginDependency\\PluginDependency",
        "label": {
            "de-DE": "Plugin mit Plugin-Abhängigkeiten",
            "en-GB": "Plugin with plugin dependencies"
        },
        "description": {
            "de-DE": "Plugin mit Plugin-Abhängigkeiten",
            "en-GB": "Plugin with plugin dependencies"
        }
    },
    "autoload": {
        "psr-4": {
            "Swag\\PluginDependency\\": "src/"
        }
    }
}
```

Have a detailed look at the `require` keyword, which now requires both the Shopware 6 version, which **always**
has to be mentioned in your `composer.json`, as well as the previously mentioned plugin and its version.
Just as in composer itself, you can also use version wildcards, such as `v1.0.*` to only require the other plugin's
minor version to be 1.1, not considering the patch version.

Now your plugin isn't installable anymore, until that requirement is fulfilled.

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-plugin-dependency).
