[titleEn]: <>(Optional requirements of a plugin)
[metaDescriptionEn]: <>(This HowTo will give an example on handle optional requirements of your plugin.)
[hash]: <>(article:how_to_plugin_requirements)

## Overview

This HowTo will show you how to handle optional requirements of your plugin.

## Starting point

Given your plugin could provide additional features, but for that it needs another plugin.
But your plugin should also be runnable, if the other plugin is not in the system.
So adding the other plugin to the `require` section of your `composer.json` file is not an option,
because that would mean, the other plugin has to be installed and active in the system.

This is your example service, which extends from a service of another plugin:
```php
<?php declare(strict_types=1);

namespace MyPlugin\Service;

use OtherPlugin\Service\OtherService;

class MyService extends OtherService
{
    public function doStuff(): void
    {
        // do something
    }
}
```

This is how your regular `Resources\config\services.xml` file would look like: 
```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="MyPlugin\Service\MyService" parent="OtherPlugin\Service\OtherService"/>
    </services>
</container>
```

If you now install and active your plugin, Shopware will crash if the OtherPlugin is not installed and also activated.
To prevent that, you need to register the `services.xml` manually,
and before doing so, you also need to check some preconditions. 

First, rename or move the `Resources\config\services.xml` file, to prevent Shopware from autoloading it,
e.g to `other_plugin_extension.xml`.

Now you need to overwrite the `build` method in your plugin base class.

```php
<?php declare(strict_types=1);

namespace MyPlugin;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Kernel;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class MyPlugin extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $activePlugins = $container->getParameter('kernel.active_plugins');
        if (!isset($activePlugins[\OtherPlugin\OtherPlugin::class])) {
            return;
        }

        // Only load relevant classes if OtherPlugin is available
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/Resources/config'));
        $loader->load('other_plugin_extension.xml');
    }
}
```

Use the `kernel.active_plugins` parameter to get information of the active plugins.
If the plugin is not found in the system or not active, do nothing.

If the plugin is present in the system and also active, we could use the XmlFileLoader to load our `other_plugin_extension.xml`,
which contains the declaration of the service which is extending from the other plugin.
