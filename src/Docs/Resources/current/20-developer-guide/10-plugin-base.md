[titleEn]: <>(Plugin base)
[hash]: <>(article:developer_plugin_base)

## Plugin system guide

Plugins in Shopware are essentially an extension of
[Symfony bundles](https://symfony.com/doc/current/bundles.html#creating-a-bundle)
. Such bundles and plugins can provide their own resources like assets,
controllers, services or tests. To reduce friction when programming plugins for
Shopware, there's an abstract base class, which every plugin extends from - the
[plugin base class](./../60-references-internals/40-plugins/020-plugin-base-class.md)
. In this class there are helper methods to initialise parameters like the
plugin's name and root path in the
[dependency injection container](https://symfony.com/doc/current/service_container.html#service-parameters)
. Also, each plugin is represented as a composer package and may for example
define dependencies this way.

## Creating a new plugin

### The files

What's needed to create a new plugin in Shopware 6 is nothing more than a class
extending the plugin base class along with a `composer.json` in the correct
directory. The plugin's class name and directory are determined by its name. You
may choose a name freely, but it should be prefixed by convention with a unique
shorthand for the developer or the developing company respectively. The file
structure for a plugin integrating a monitoring solution could for example look
like this:

```
./
+-- SwagMonitoring/
    +-- composer.json
    +-- src/
        +-- SwagMonitoring.php
```

### The content

The plugin's base class `SwagMonitoring.php` needs to extend Shopware's
`Plugin` class. Apart from that, no other information is needed in this file:

```php
<?php declare(strict_types=1);

namespace Swag\Monitoring;

use Shopware\Core\Framework\Plugin;

class SwagMonitoring extends Plugin
{
}
```

The information in the `composer.json` can be interpreted by composer of course,
but is also read by Shopware. You can define dependencies as well as a license
and other information. You may also store metadata
about your plugin here using the `extra` property. For Shopware to be able to
find the plugin when it is installed via composer, the `type` needs to be set to
`shopware-platform-plugin`. A basic `composer.json` could look like this:

```json
{
    "name": "swag/monitoring",
    "description": "Lorem ipsum dolor sit amet",
    "version": "v1.0.0",
    "type": "shopware-platform-plugin",
    "license": "AGPL-3.0-or-later",
    "authors": [
        {
            "name": "shopware AG",
            "role": "Manufacturer"
        }
    ],
    "require": {
        "shopware/core": "6.2.*"
    },
    "extra": {
        "shopware-plugin-class": "Swag\\Monitoring\\SwagMonitoring",
        "plugin-icon": "Resources/config/plugin.png",
        "label": {
            "de-DE": "Monitoring Plugin",
            "en-GB": "Monitoring plugin"
        }
    },
    "autoload": {
        "psr-4": {
            "Swag\\Monitoring\\": "src/"
        }
    }
}
```

### Plugin icon
A plugin can be shipped with an icon which will be rendered in the administration. Therefore a 40 x 40 px png file can be shipped with the following path/filename: `SwagStorePlugin/src/Resources/config/plugin.png`. More information in the [plugin meta information reference](./../60-references-internals/40-plugins/050-plugin-information.md).

## Install

### The Symfony part

Upon instantiation, like with bundles, a plugin's `build` method is called,
which allows the plugin to register
[compiler passes](https://symfony.com/doc/current/service_container/compiler_passes.html)
or load additional
[service definitions](https://symfony.com/doc/current/bundles/extension.html#using-the-load-method)
.

### The Shopware part

Each plugin may also have an `install` method. This method is Shopware-specific
and can contain code which initialises the state of the plugin, for example
system-specific configuration which can't be determined at the time the
[migrations](./../60-references-internals/40-plugins/080-plugin-migrations.md)
are run.

## Uninstall

When a plugin is being uninstalled, its `uninstall` method is called. This
method receives an `UninstallContext` which contains some information about the
plugin and the uninstallation process, the most important being `keepUserData`.
The `keepUserData` variable equals `true`, when the user uninstalling the
plugin has specified, that they'd like to keep all data produced by the plugin
during its lifetime. Usually this amounts to the database entries and tables the
plugin has produced.

Note, that it is up to the plugin author, to take this configuration into
account. When implementing the `uninstall` method of your plugin, check if the
user would like to keep the plugin's data:

```php
/**
 * @inheritDoc
 */
public function uninstall(UninstallContext $context): void
{
    if ($context->keepUserData()) {
        parent::uninstall($context);

        return;
    }

    // Remove all traces of your plugin
}
```

## Plugin configuration

To allow the users of your plugin to change the plugin's behaviour, you may add
a `config.xml`. This file is interpreted by Shopware to automatically
create a settings form in the administration. This is how a basic `config.xml`
might look:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/trunk/src/Core/System/SystemConfig/Schema/config.xsd">

    <card>
        <title>Minimal configuration</title>
        <input-field>
            <name>example</name>
        </input-field>
    </card>
</config>
```

For more information about plugin configuration and the `config.xml`, head
over to the
[plugin configuration](./../60-references-internals/40-plugins/070-plugin-config.md)
section.
