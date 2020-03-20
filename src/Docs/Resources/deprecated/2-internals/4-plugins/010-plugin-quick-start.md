[titleEn]: <>(Plugin quick start)
[hash]: <>(article:plugin_quick_start)

To be able to introduce extensions into the system, the core comes with an integrated plugin system.
Plugins are [Symfony Bundles](https://symfony.com/doc/current/bundles.html) which can be activated and deactivated via the [plugin commands](./030-plugin-commands.md).
A plugin can change the behavior of the system including: 
* Listening for events and executing afterwards ([Listening to events via Subscriber](./../../4-how-to/040-register-subscriber.md))
* Define new services, extend existing ones or exchange them completely to implement your custom logic and business cases ([Creating a service](./../../4-how-to/070-add-service.md))
* Include entities in the system and/or extend existing ones ([Custom entities via plugin](./../../4-how-to/050-custom-entity.md))

This document will give you a brief introduction on how to create your very first own plugin, including some
basic setup, e.g. registering your first service or creating a new controller.

At the very end, your plugin structure should look something like this:
```
<project root>
└── custom
    └── plugins
        └── PluginQuickStart
            ├── src
            │   ├── Controller
            │   │   └── MyController.php
            │   ├── Resources
            │   │   ├── config
            │   │   │   ├── config.xml
            │   │   │   ├── routes.xml
            │   │   │   └── services.xml
            │   ├── Service
            │   │   └──  MyService.php
            │   ├── Subscriber
            │   │   └── MySubscriber.php
            │   └── PluginQuickStart.php
            └── composer.json
```

## Plugin base

The corresponding plugin sources can be stored in the `/custom/plugins/<plugin-directory>/src` directory.
It is also possible to `require` your plugin via composer, since Shopware searches the `vendor` directory for packages with the type `shopware-platform-plugin`.

Each plugin has to come with a base class, which serves as an entry point into the system.
Just create a new directory inside of `custom/plugins` and choose a proper name for your plugin directory, in this example `PluginQuickStart` is used.
For better structuring, we highly recommend creating a `src` directory next, in which you place your plugin's base class.
Therefore create a new .php file inside of your `<plugin root>/src` directory.

Note: Whatever name you choose for your base class file, it is also highly recommended to use the same name
for the plugin's namespace, which should then consist of your individual vendor prefix and your plugin name.

The following example should shed some light into this matter.
Your plugins base class should look like this, if you were to name your plugin "PluginQuickStart" and your vendor prefix would be "swag":

```php
<?php declare(strict_types=1);

namespace Swag\PluginQuickStart;

use Shopware\Core\Framework\Plugin;

class PluginQuickStart extends Plugin
{
}
```
*PluginQuickStart/src/PluginQuickStart.php*

The directory structure could then look like this:

```
<project root>
└── custom
    └── plugins
        └── PluginQuickStart
            └── src
                └── PluginQuickStart.php
```

## Plugin meta data

Another requirement for a working plugin is the `composer.json` file.
It contains all the necessary meta data of your plugin, e.g. the plugin version, its supported Shopware 6 versions,
the plugin description, the plugin title and many more.
This has to be located in your plugin **root** directory.

Here's a brief example of how this file could look like:
```json
{
    "name": "swag/plugin-quick-start",
    "description": "Plugin quick start plugin",
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
        "shopware/core": "6.1.*"
    },
    "extra": {
        "shopware-plugin-class": "Swag\\PluginQuickStart\\PluginQuickStart",
        "label": {
            "de-DE": "Beispiel Plugin",
            "en-GB": "Example Plugin"
        },
        "description": {
            "de-DE": "Deutsche Beschreibung des Plugins",
            "en-GB": "English description of the plugin"
        }
    },
    "autoload": {
        "psr-4": {
            "Swag\\PluginQuickStart\\": "src/"
        }
    }
}
```
*PluginQuickStart/composer.json*

Read [here](./050-plugin-information.md) for more information about the content of the composer.json file.

## Installing the plugin

Now, that you've created the two necessary plugin files, you're able to install the plugin.
This is done using one of the [plugin commands](./030-plugin-commands.md).

Starting in your **project root** directory, check if your plugin is already known to Shopware by running the command `bin/console plugin:list`. If your new plugin is missing, execute the command `bin/console plugin:refresh`. Afterwards run the command `bin/console plugin:install --activate --clearCache PluginQuickStart` to install and activate the plugin.

## Plugin configuration

When shipping a plugin to your customer, you might want to ship the plugin with some built-in configurations.
This way you can make sure your customers can configure your plugin to perfectly fit their needs.

Shopware 6 supports this out of the box by adding a `config.xml` file to your plugin.
By default, this should be placed relative to your plugin's base class in a directory called `Resources/config`.
Both the file naming as well as the directory naming is important here for the auto-loading of the `config.xml` file to work as intended.
In this example, the location would be the following: `PluginQuickStart/src/Resources/config/config.xml`

The default location can be changed in your plugin's base class though. For a detailed explanation about this, have a look at our guide about the [plugin's base class](./020-plugin-base-class.md#getConfigPath).

For this tutorial, a simple configuration containing a single text field is used:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/master/src/Core/System/SystemConfig/Schema/config.xsd">
    
    <card>
        <title>Minimal configuration</title>
        <title lang="de-DE">Minimale Konfiguration</title>
        <input-field>
            <name>example</name>
            <label>Example Label EN</label>
            <label lang="de-DE">Beispiel Label DE</label>
        </input-field>
    </card>
</config>
```
*PluginQuickStart/src/Resources/config/config.xml*

This configuration would now create a new text field inside a panel with the title "Minimal configuration", depending on
the language chosen. Also, the text field's technical name is `example` in this case, and so would be the label if you didn't provide a specific label for it.

**The technical name is the identifier you'll use later on to retrieve the value the user provided.**  It's name can't contain any spaces.

Those will be rendered into the administration settings.

For a more detailed guide on how to setup the `config.xml` and which input types exist, head over to the detailed [plugin configuration](./070-plugin-config.md) guide.

## Listening to events via Subscriber

You registered the plugin into Shopware 6, created a config for it and even installed and activated it afterwards.
Unfortunately, you're not really doing anything with your plugin as of yet.

One of the main purposes of a plugin is listening to several system events and then executing code once an event is dispatched.
In order to do so, Shopware 6 makes use of the [Symfony subscribers](https://symfony.com/doc/current/components/event_dispatcher.html#using-event-subscribers).

### Subscriber class

Before you can register your subscriber, you need the subscriber class itself first.
The subscriber class has to implement the `EventSubscriberInterface` and therefore it's required static method `getSubscribedEvents`.
Inside the method you'll have to return an associative array, where the key represents the event to listen to
and the actual value pointing to the method to execute once the event was dispatched.

In this example the `product.loaded` event is used, which is triggered once one or more products were loaded..
The subscriber class could be placed into a directory named `Subscriber` in the plugin's `src` directory, the actual
naming for both the file as well as the directory `Subscriber` is irrelevant here though.

An example of a subscriber could look like this:
```php
<?php declare(strict_types=1);

namespace Swag\PluginQuickStart\Subscriber;

use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return[
            ProductEvents::PRODUCT_LOADED_EVENT => 'onProductsLoaded'
        ];
    }

    public function onProductsLoaded(EntityLoadedEvent $event)
    {
        // Do something
        // E.g. work with the loaded entities: $event->getEntities()
    }
}
```
*PluginQuickStart/src/Subscriber/MySubscriber.php*

The subscriber would now listen to the event `example_event` and once the event is dispatched, the method `onExampleEvent` is executed.

### The services.xml

Your subscriber now has to be registered into the [DI container](https://symfony.com/doc/current/service_container.html).
In Shopware 6, the services in the DI container are defined in XML.

A `services.xml` file is automatically loaded, if you place it into the proper directory.
This works just like the plugin's `config.xml` explained above. Once again, the proper directory for the `services.xml` has to be located relative
to your plugin's base class in a directory called `Resources/config`.

Therefore the path to the `services.xml` would look like this: `PluginQuickStart/src/Resources/config/services.xml`

This location can also be adjusted by overwriting the `getContainerPath` method of your plugin's base class.
For a detailed explanation on how to override this, have a look at our guide about the [plugin's base class](./020-plugin-base-class.md#getContainerPath).

### Registering your subscriber

You've created a subscriber class and you've made sure your plugin is actually containing a `services.xml` file.

It could then contain the following XML:

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Swag\PluginQuickStart\Subscriber\MySubscriber">
            <tag name="kernel.event_subscriber"/>
        </service>
    </services>
</container>
```
*PluginQuickStart/src/Resources/config/services.xml*

The service ID represents the fully qualified class name of your subscriber class.
If you chose another name for the `Subscriber` directory, make sure to change it here as well.

This way you've not only registered your own service via the `<service>` element, you also made sure it gets tagged
with the `kernel.event_subscriber` tag, which is the Symfony default tag for subscribers.

Your subscriber is now fully integrated:
- The subscriber class exists and it listens to an event
- The subscriber is mentioned in the `services.xml` file

## Creating a controller

Another common thing to be done in a plugin is registering a custom controller, e.g. to be used as a new custom API endpoint.
For this case you could create a new directory called `Controller` inside the `src` directory , but once again, the naming and structure can be freely chosen here.

Inside this directory, just create a new PHP class for the controller, in this example it's named `MyController`.
The controller should extend from the Symfony `AbstractController` class, since it provides common features needed in the
controller context.

An example of the `MyController` controller could look like this:
```PHP
<?php declare(strict_types=1);

namespace Swag\PluginQuickStart\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @RouteScope(scopes={"api"})
 */
class MyController extends AbstractController
{
    /**
     * @Route("/api/v{version}/_action/swag/my-api-action", name="api.action.swag.my-api-action", methods={"GET"})
     */
    public function myFirstApi(Request $request, Context $context): JsonResponse
    {
        return new JsonResponse(['You successfully created your first controller route']);
    }
}
```
*PluginQuickStart/src/Controller/MyController.php*

Important to notice is the `@Route` annotation above the `myFirstApi`, which basically defines the actual route to access the controller.

Additional to that, Shopware 6 needs to learn where to search for your controller in the first place.
This is done by adding a `routes.xml` file into a `src/Resources/config/` directory.
The naming for the directory is not only suggested, it is highly recommended again here, so the auto-loading works.
This is done by looking for any .xml file, whose path contains `routes`, so the path `src/Resources/config/routes/example.xml` would also work.

The XML file then simply points to the directory containing your controller, in this example it would be `Controller`.

Here's the example for the `routes.xml` content:
```xml
<?xml version="1.0" encoding="UTF-8" ?>

<routes xmlns="http://symfony.com/schema/routing"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/routing
    http://symfony.com/schema/routing/routing-1.0.xsd">

    <import resource="../../Controller" type="annotation" />
</routes>
```
*PluginQuickStart/src/Resources/config/routes.xml*

Since Shopware 6 uses Symfony fullstack, it's technically also possible to use configuration files written in YML or PHP.
Yet, Shopware 6 is looking for XML files by default. This can be overridden in the plugin's [base class](./020-plugin-base-class.md).
An representation of the same routes configuration using a YAML or PHP file instead, can be found in the Symfony documentation
about [external routing resources](https://symfony.com/doc/current/routing/external_resources.html).

Now the controller should be fully working and accessible using the route mentioned in the method's `@Route` annotation.
Since we've created an API route here, an authorization token is still necessary to actually access our controller.
Remove the 'api' from the route to circumvent the authorization for testing purposes or get more into how Shopware 6 Admin API works [here](./../../3-api/010-admin-api.md). 

## Creating a service

Since you don't want any business logic to be executed inside of a controller or subscriber, you usually want to put your business logic
into services. Earlier in this tutorial you already created a `services.xml` file, and as the name suggests, you can also define new services in there.

Sticking to the previous examples, you could just name it `MyService` and place it into a `src/Service` directory.

```php
<?php declare(strict_types=1);

namespace Swag\PluginQuickStart\Service;

class MyService
{
    public function doSomething(): void
    {
    }
}
```
*PluginQuickStart/src/Service/MyService.php*

In order to access it via the [DI container](https://symfony.com/doc/current/service_container.html), you need to mention the service in the said `services.xml` file:

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Swag\PluginQuickStart\Subscriber\MySubscriber">
            <tag name="kernel.event_subscriber"/>
        </service>
        
        <service id="Swag\PluginQuickStart\Service\MyService" />
    </services>
</container>
```
*PluginQuickStart/src/Resources/config/services.xml*

Your service is now part of the DI container, but only as a [private service](https://symfony.com/doc/current/service_container/alias_private.html).
An example usage would be in your new controller.

```PHP
<?php declare(strict_types=1);

namespace Swag\PluginQuickStart\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Swag\PluginQuickStart\Service\MyService;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @RouteScope(scopes={"api"})
 */
class MyController extends AbstractController
{
    /**
     * @var MyService
     */
    private $myService;

    public function __construct(MyService $myService)
    {
        $this->myService = $myService;
    }

    /**
     * @Route("/api/v{version}/_action/swag/my-api-action", name="api.action.swag.my-api-action", methods={"GET"})
     */
    public function myFirstApi(Request $request, Context $context): JsonResponse
    {
        $this->myService->doSomething();
        return new JsonResponse(['You successfully created your first controller route']);
    }
}
```

Notice the new constructor and it's parameter `MyService $myService`, which now has to be applied to the controller by adjusting the `services.xml` file once more.

The controller will be added as another normal service, yet it has to be a public one.
Your service `MyService` is then applied as an argument to the service definition, just like it's suggested
in the [Symfony documentation](https://symfony.com/doc/current/service_container.html#choose-a-specific-service) about the DI container.

Your `services.xml` should now look like this:
```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Swag\PluginQuickStart\Subscriber\MySubscriber">
            <tag name="kernel.event_subscriber"/>
        </service>

        <service id="Swag\PluginQuickStart\Service\MyService" />

        <service id="Swag\PluginQuickStart\Controller\MyController" public="true">
            <argument type="service" id="Swag\PluginQuickStart\Service\MyService" />
        </service>
    </services>
</container>
```

# Helpful plugin services

## Logging

There is a logging service factory that can create loggers for any case.
The logging service has to be registered manually like this:
```
<?xml version="1.0" ?>
<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
    <services>
        <service id="plugin_quick_start.logger" class="Monolog\Logger">
            <factory service="Shopware\Core\Framework\Log\LoggerFactory" method="createRotating"/>
            <argument type="string">plugin_quick_start</argument>
        </service>
        <service id="plugin_quick_start.foobar.logger" class="Monolog\Logger">
            <factory service="Shopware\Core\Framework\Log\LoggerFactory" method="createRotating"/>
            <argument type="string">plugin_quick_start_foobar</argument>
        </service>
    </services>
</container>

```
It is a rotating file logger that has its specific file prefix (e.g. `var/logs/plugin_quick_start_dev-2019-03-15.log`).

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-plugin-quick-start).
