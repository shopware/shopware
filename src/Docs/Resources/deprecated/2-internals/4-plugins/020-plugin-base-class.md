[titleEn]: <>(Plugin - Base Class)
[hash]: <>(article:plugin_base)

# Overview
In this guide, you will learn what a plugin base class is and how to use it.
Below you'll find a valid plugin file structure.

```
custom
└── plugins
    └──  BaseClass
         ├── src
         │   └──BaseClass.php
         └── composer.json
```
*File Structure*

Read more about the `composer.json` file [here](./050-plugin-information.md).

# Base Class
Your plugin base class is used, to configure your plugin and manage plugin lifecycle events such as `update` and `install`.
Every plugin base class must extend from the `Shopware\Core\Framework\Plugin` class.
Take a look at the most minimalistic plugin base class:

```php
<?php declare(strict_types=1);

namespace Swag\BaseClass;

use Shopware\Core\Framework\Plugin;

class BaseClass extends Plugin
{
}
```
*BaseClass/src/BaseClass.php*

Right now you got a valid base class without any functionality.
Below you find lists of methods you can overwrite in your plugin base class.

## Plugin lifecycle

Those methods are used to deal with certain lifecycle events of your plugin.
They are only executed once, when the user triggers one of those actions.
The core Shopware DI container is available for all of them.

| Method                                                | Arguments                                                      | Usage                                     |
|-------------------------------------------------------|----------------------------------------------------------------|-------------------------------------------|
| [install](./020-plugin-base-class.md#install)         | [InstallContext](./040-plugin-contexts.md#installContext)      | Called while your plugin gets installed   |
| [postInstall](./020-plugin-base-class.md#postInstall) | [InstallContext](./040-plugin-contexts.md#installContext)      | Called after your plugin got installed    |
| [update](./020-plugin-base-class.md#update)           | [UpdateContext](./040-plugin-contexts.md#updateContext)        | Called while your plugin gets updated     |
| [postUpdate](./020-plugin-base-class.md#postUpdate)   | [UpdateContext](./040-plugin-contexts.md#updateContext)        | Called after your plugin got updated      |
| [activate](./020-plugin-base-class.md#activate)       | [ActivateContext](./040-plugin-contexts.md#activateContext)    | Called while your plugin gets activated   |
| [deactivate](./020-plugin-base-class.md#deactivate)   | [DeactivateContext](./040-plugin-contexts.md#deactivateContext)| Called while your plugin gets deactivated |
| [uninstall](./020-plugin-base-class.md#uninstall)     | [UninstallContext](./040-plugin-contexts.md#uninstallContext)  | Called while your plugin gets uninstalled |

Also have a look at this diagram for a more detailed overview of the lifecycle methods:
![Plugin lifecycle](./img/plugin-lifecycle.png)

### install
You can use this method to execute code you need to run while your plugin gets installed.
For example, you could use this method to create a new payment method.

```php
<?php declare(strict_types=1);

namespace Swag\BaseClass;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;

class BaseClass extends Plugin
{
    public function install(InstallContext $context): void
    {
        // your code you need to execute while installation
    }
}
```
*Please note, if your code fails or throws an exception, your plugin will not be installed.*

### postInstall
You can use this method to execute code you need to run after your plugin is installed and migrations have run.

```php
<?php declare(strict_types=1);

namespace Swag\BaseClass;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;

class BaseClass extends Plugin
{
    public function postInstall(InstallContext $context): void
    {
        //your code you need to execute after your plugin gets installed
    }
}
```
*Please note, if your code fails or throws an exception, your plugin will not be activated in the same step.*

### update
You can use this method to execute code you need to run while your plugin gets updated.

```php
<?php declare(strict_types=1);

namespace Swag\BaseClass;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class BaseClass extends Plugin
{
    public function update(UpdateContext $context): void
    {
       // your code you need to execute while your plugin gets updated
    }

}
```
*Please note, if your code fails or throws an exception, your plugin will not be updated.*

### postUpdate
You can use this method, to execute code you need to run after your plugin is updated and migrations have run.

```php
<?php declare(strict_types=1);

namespace Swag\BaseClass;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class BaseClass extends Plugin
{
    public function postUpdate(UpdateContext $context): void
    {
        // your code you need to execute after your plugin is updated
    }
}
```

### activate
You can use this method, to execute code you need to run while your plugin gets activated.

```php
<?php declare(strict_types=1);

namespace Swag\BaseClass;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;

class BaseClass extends Plugin
{
    public function activate(ActivateContext $context): void
    {
        // your code you need to execute while your plugin gets activated
    }

}
```
*Please note, if your code fails or throws an exception your plugin will not be activated.*

### deactivate
You can use this method, to execute code you need to run while your plugin gets deactivated.

```php
<?php declare(strict_types=1);

namespace Swag\BaseClass;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;

class BaseClass extends Plugin
{
    public function deactivate(DeactivateContext $context): void
    {
        // your code you need to run while your plugin gets deactivated
    }
}
```
*Please note, if your code fails or throws an exception, your plugin will not be deactivated.*

### uninstall
You can use this method, to execute code you need to run while your plugin gets uninstalled.

```php
<?php declare(strict_types=1);

namespace Swag\BaseClass;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class BaseClass extends Plugin
{
    public function uninstall(UninstallContext $context): void
    {
        // your code you need to execute while your plugin gets uninstalled
    }
}
```
*Please note, if your code fails or throws an exception, your plugin will not be uninstalled.*

## Configuring your plugin

Those methods are called to configure your plugin, e.g. configuring the path for your `routes.xml` file or to add `ActionEvents`.
These are executed with each request, so be careful with them, due to performance and fatal error issues.

| Method                                                                              | Arguments                                   | Usage                                                                                                            | Container available |
|-------------------------------------------------------------------------------------|---------------------------------------------|------------------------------------------------------------------------------------------------------------------|---------------------|
| [build](./020-plugin-base-class.md#build)                                           | ContainerBuilder                            | Called while Symfony builds the [DI container](https://symfony.com/doc/current/service_container.html)           |      Partially      |
| [configureRoutes](./020-plugin-base-class.md#configureRoutes)                       | RouteCollectionBuilder, string $environment | Called on each kernel boot to register your controller routes                                                    |         No          |
| [getMigrationNamespace](./020-plugin-base-class.md#getMigrationNamespace)           | N/A                                         | Called whenever migrations get executed to add your migration namespace to the migration collection              |         Yes         |
| [getActionEvents](./020-plugin-base-class.md#getActionEvents)                       | N/A                                         | Registers action events for your plugin                                                                          |      Partially      |

### build

You can use this method to change the DI container while it's still being built, e.g. by adding new compiler passes.
Have a look [here](https://symfony.com/doc/current/service_container/compiler_passes.html) to figure out how to work with a Symfony compiler pass.

```php
<?php declare(strict_types=1);

namespace Swag\BaseClass;

use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class BaseClass extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
    }
}
```
*Please note, if your code fails or throws an exception, the `Symfony Kernel` will no longer be able to boot.*

### configureRoutes

You can use this method, to configure routing for your plugin.
Per default, you can configure your routes in `YourPlugin/src/Resources/config/routes.xml`.
Click [here](./../../4-how-to/020-api-controller.md#Loading the controllers via routes.xml) if you want to learn more.

```php
<?php declare(strict_types=1);

namespace Swag\BaseClass;

use Shopware\Core\Framework\Plugin;
use Symfony\Component\Routing\RouteCollectionBuilder;

class BaseClass extends Plugin
{
    public function configureRoutes(RouteCollectionBuilder $routes, string $environment): void
    {
        $routes->import(__DIR__ . '/my_routes.xml');
    }
}
```
*Please note, if your code fails or throws an exception, the `Symfony Kernel` will no longer be able to boot.*

### getMigrationNamespace

You can use this method, to configure a custom migration namespace.
For your example plugin `BaseClass` the default migration namespace would be `BaseClass\Migration`.
If you're not familiar with plugin migrations yet, make sure to read our guide about the [plugin migration system](./080-plugin-migrations.md).

```php
<?php declare(strict_types=1);

namespace Swag\BaseClass;

use Shopware\Core\Framework\Plugin;

class BaseClass extends Plugin
{
    public function getMigrationNamespace(): string
    {
        return 'Swag\BaseClass\MyMigrationNamespace';
    }
}
```
*Please note, if your code fails or throws an exception, your plugin migrations will no longer work.*

## Plugin boot process

This method is executed at a very early point of the Shopware stack, but only if your plugin is active already.

|Method                                   | Arguments                   | Usage                                      | Container available |
|-----------------------------------------|-----------------------------|--------------------------------------------|---------------------|
| [boot](./020-plugin-base-class.md#boot) | N/A                         | Called while the Shopware kernel is booted |         Yes         |

## boot

Boots your plugin and is called when the kernel gets booted.
The container is available here already.

```php
<?php declare(strict_types=1);

namespace Swag\BaseClass;

use Shopware\Core\Framework\Plugin;

class BaseClass extends Plugin
{
    public function boot(): void
    {
        parent::boot();
    }
}
```

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-base-class).
