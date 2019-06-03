[titleEn]: <>(Getting started with plugins)
[titleDe]: <>(Getting started with plugins)
[wikiUrl]: <>(../plugin-system/getting-started?category=shopware-platform-en/plugin-system)

To be able to introduce extensions into the system, the core comes with an integrated plugin system.
Plugins are [Symfony Bundles](https://symfony.com/doc/current/bundles.html) which can be activated and deactivated via the [bin/console plugin:* commands](020-plugin-commands.md).
A plugin can change the behavior of the system including: 
* Create custom events and listen to existing ones
* Include entities in the system and/or extend existing ones
* Define new services, extend existing ones or exchange them completely to implement your custom logic and business cases

## Plugin base
The corresponding plugin sources can be stored under `/custom/plugins`.
It is also possible to `require` your plugin via composer.
Shopware searches the `vendor` directory for packages with the type `shopware-platform-plugin`.

As an entry point into the system, each plugin must have a base class.
As convention you have to create a directory under `/custome/plugins` which has the same name of your plugin.
This directory contains the base class which must have the same name as your plugin.
The base class must define a namespace which must also have the same name as your plugin.
The following example shows the basic structure of a plugin with the name "GettingStarted".

```php
<?php declare(strict_types=1);

//sources of custom/plugins/GettingStarted/GettingStarted.php

namespace GettingStarted;

use Shopware\Core\Framework\Plugin;

class GettingStarted extends Plugin
{
}
```

Now add a `composer.json` file to your plugin directory.
Read [here](050-plugin-information.md) for more information about the content of this file.

If you add these two files, the plugin can be registered and installed in the system.

Subsequently, further functions can be integrated into the base class to react to certain actions in the system.
Read more about the lifecycle and kernel methods of a plugin [here](030-plugin-base-class.md).

Run `bin/console plugin:install --activate GettingStarted` to install and activate the plugin.

## Include services.xml
The central place to extend Shopware 6 is the [DI container](https://symfony.com/doc/current/service_container.html). 
In the platform, the services in the DI container are defined in XML.
To integrate your own `services.xml` in your plugin, the `build` function of your base class has to be overwritten:

```php
<?php declare(strict_types=1);

namespace GettingStarted;

use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Config\FileLocator;

class GettingStarted extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__));
        $loader->load('./DependencyInjection/services.xml');
    }
    
}
``` 

Here are some additional articles regarding plugins:

[Extend the administration](../10-administration/01-administration-start-development.md)

[Payment plugins](../50-checkout/70-payment.md)

[Extensions for the data abstraction layer](../20-data-abstraction-layer/4-extensions.md)
