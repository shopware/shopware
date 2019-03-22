[titleEn]: <>(Plugin - Base Class)
[titleDe]: <>(Plugin - Base Class)
[wikiUrl]: <>(../plugin-system/plugin-base-class?category=shopware-platform-en/plugin-system)

## Overview
In this guide, you will learn what a plugin base class is and how to use it.
Below you'll find a valid plugin file structure.

```
custom
└── plugins
    └──  SwagExample
         ├── SwagExample.php
         └── composer.json
```
*File Structure*

Read more about the `composer.json` file [here](050-plugin-information.md).

## Base Class
Your plugin base class is used, to configure your plugin and manage plugin lifecycle events such as `update` and `install`.
Every plugin base class must extend from `Shopware\Core\Framework\Plugin`.
Take a look at the most minimalistic plugin base class:

```php
<?php declare(strict_types=1);

namespace SwagExample;

use Shopware\Core\Framework\Plugin;

class SwagExample extends Plugin
{
}
```
*SwagExample.php*

Right now you got a valid base class without any functionality.
Below you find a list of methods you can overwrite in your plugin base class.

|Method                                               | Arguments                                                      | Usage                                                                                               |
|-----------------------------------------------------|----------------------------------------------------------------|-----------------------------------------------------------------------------------------------------|
| [isActive()](#isActive())                           | N/A                                                            | Identifies if your plugin is active                                                                 |
| [install()](#install())                             | [InstallContext](./040-plugin-contexts.md#installContext)       | Called while your plugin gets installed                                                             |
| [postInstall()](#postInstall())                     | [InstallContext](./040-plugin-contexts.md#installContext)       | Called after your plugin gets installed                                                             |
| [update()](#update())                               | [UpdateContext](./040-plugin-contexts.md#updateContext)         | Called while your plugin gets updated                                                               |
| [postUpdate()](#postUpdate())                       | [UpdateContext](./040-plugin-contexts.md#updateContext)         | Called after your plugin gets updated                                                               |
| [activate()](#activate())                           | [ActivateContext](./040-plugin-contexts.md#activateContext)     | Called while your plugin gets activated                                                             |
| [deactivate()](#deactivate())                       | [DeactivateContext](./040-plugin-contexts.md#deactivateContext) | Called while your plugin gets deactivated                                                           |
| [uninstall()](#uninstall())                         | [UninstallContext](./040-plugin-contexts.md#uninstallContext)   | Called while your plugin gets uninstalled                                                           |
| [boot()](#boot())                                   | N/A                                                            | Called while the Shopware kernel is booted                                                          |
| [build()](#build())                                 | ContainerBuilder                                               | Called while Symfony builds the Dependency-Injection-Container                                      |
| [configureRoutes()](#configureRoutes())             | RouteCollectionBuilder, string $environment                    | Called on each kernel boot to register your controller routes                                       |
| [getMigrationNamespace()](#getMigrationNamespace()) | N/A                                                            | Called whenever migrations get executed to add your migration namespace to the migration collection |
| [getContainerPrefix()](#getContainerPrefix())       | N/A                                                            | Prefixes automatic service registrations like filesystems for example                               |
*Base Class Method List*

## isActive()
Your `isActive()` method gets called by the `Shopware Plugin-System` to determine if your plugin is active.
Without overwriting this method it simply returns the field `$active`.

```php
<?php declare(strict_types=1);

namespace SwagExample;

use Shopware\Core\Framework\Plugin;

class SwagExample extends Plugin
{
    public function isActive(): bool
    {
        if(/*your condition*/) {
            return true;
        }

        return false;
    }
}
```
*Please note, if you overwrite this method and your code fails, your plugin may never be shown as active.*

## install()
You can use this method to execute code you need to run while your plugin gets installed.
For example, you could use this method to create a new payment method.

```php
<?php declare(strict_types=1);

namespace SwagExample;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;

class SwagExample extends Plugin
{
    public function install(InstallContext $context): void
    {
        //your code you need to execute while installation
    }
}
```
*Please note, if your code fails or throws an exception, your plugin will not be installed.*

## postInstall()
You can use this method to execute code you need to run after your plugin is installed and migrations have run.

```php
<?php declare(strict_types=1);

namespace SwagExample;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;

class SwagExample extends Plugin
{
    public function postInstall(InstallContext $context): void
    {
        //your code you need to execute after your plugin gets installed
    }
}
```
*Please note, if your code fails or throws an exception, your plugin will not be activated in the same step.*

## update()
You can use this method to execute code you need to run while your plugin gets updated.

```php
<?php declare(strict_types=1);

namespace SwagExample;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class SwagExample extends Plugin
{
    public function update(UpdateContext $context): void
    {
       // your code you need to execute while your plugin gets updated
    }

}
```
*Please note, if your code fails or throws an exception, your plugin will not be updated.*

## postUpdate()
You can use this method, to execute code you need to run after your plugin is updated and migrations have run.

```php
<?php declare(strict_types=1);

namespace SwagExample;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class SwagExample extends Plugin
{
    public function postUpdate(UpdateContext $context): void
    {
        // your code you need to execute after your plugin is updated
    }
}
```

## activate()
You can use this method, to execute code you need to run while your plugin gets activated.

```php
<?php declare(strict_types=1);

namespace SwagExample;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;

class SwagExample extends Plugin
{
    public function activate(ActivateContext $context): void
    {
        // your code you need to execute while your plugin gets activated
    }

}
```
*Please note, if your code fails or throws an exception your plugin will not be activated.*

## deactivate()
You can use this method, to execute code you need to run while your plugin gets deactivated.

```php
<?php declare(strict_types=1);

namespace SwagExample;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;

class SwagExample extends Plugin
{
    public function deactivate(DeactivateContext $context): void
    {
        // your code you need to run while your plugin gets deactivated
    }
}
```
*Please note, if your code fails or throws an exception, your plugin will not be deactivated.*

## uninstall()
You can use this method, to execute code you need to run while your plugin gets uninstalled.

```php
<?php declare(strict_types=1);

namespace SwagExample;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class SwagExample extends Plugin
{
    public function uninstall(UninstallContext $context): void
    {
        // your code you need to execute while your plugin gets uninstalled
    }
}
```
*Please note, if your code fails or throws an exception, your plugin will not be uninstalled.*

## boot()
Boots your plugin and is called when the kernel gets booted.

```php
<?php declare(strict_types=1);

namespace SwagExample;

use Shopware\Core\Framework\Plugin;

class SwagExample extends Plugin
{
    public function boot(): void
    {
        parent::boot();
    }
}
```

## build()
You can use this method, to build the `Dependency Injection Container` (DIC) how you need it.
For example, you can load your own `service.xml` into the DIC.

```php
<?php declare(strict_types=1);

namespace SwagExample;

use Shopware\Core\Framework\Plugin;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class SwagExample extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('your_service.xml');
    }
}
```
*Please note, if your code fails or throws an exception, the `Symfony Kernel` will no longer be able to boot.*

## configureRoutes()
You can use this method, to configure routing for your plugin.
Per default, you can configure your routes in `YourPlugin/Resources/routes.yaml`.
Click [here](./110-custom-api-routes.md#route-configuration) if you want to learn more.

```php
<?php declare(strict_types=1);

namespace SwagExample;

use Shopware\Core\Framework\Plugin;
use Symfony\Component\Routing\RouteCollectionBuilder;

class SwagExample extends Plugin
{
    public function configureRoutes(RouteCollectionBuilder $routes, string $environment): void
    {
        $routes->import(__DIR__ . '/my_routes.yaml');
    }
}
```
*Please note, if your code fails or throws an exception, the `Symfony Kernel` will no longer be able to boot.*

## getMigrationNamespace()
You can use this method, to configure a custom migration namespace.
For your example plugin `SwagExample` the default migration namespace would be `SwagExample\Migration`.

```php
<?php declare(strict_types=1);

namespace SwagExample;
namespace SwagExample;

use Shopware\Core\Framework\Plugin;

class SwagExample extends Plugin
{
    public function getMigrationNamespace(): string
    {
        return 'SwagExample\MyMigrationNamespace';
    }
}
```
*Please note, if your code fails or throws an exception, your plugin migrations will no longer work.*

## getContainerPrefix()
You can use this method, to configure your own container prefix.
For your example plugin `SwagExample` the default container prefix would be `swag_example`.

```php
<?php declare(strict_types=1);

namespace SwagExample;

use Shopware\Core\Framework\Plugin;

class SwagExample extends Plugin
{
    public function getContainerPrefix(): string
    {
        return 'my_container_prefix';
    }
}
```
