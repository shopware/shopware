[titleEn]: <>(Plugin quick start)

To be able to introduce extensions into the system, the core comes with an integrated plugin system.
Plugins are [Symfony Bundles](https://symfony.com/doc/current/bundles.html) which can be activated and deactivated via the [plugin commands](./030-plugin-commands.md).
A plugin can change the behavior of the system including: 
* Listening for events and executing afterwards ([Listening to events via Subscriber](#Listening to events via Subscriber)
* Define new services, extend existing ones or exchange them completely to implement your custom logic and business cases (([Creating a service](#Creating a service)))
* Include entities in the system and/or extend existing ones ([Custom entities via plugin](../../4-how-to/050-custom-entity.md)

This document will give you a brief introduction on how to create your very first own plugin, including some
basic setup, e.g. registering your first service or creating a new controller.

At the very end, your plugin structure should look something like this:
```
<project root>
└── custom
    └── plugins
        └── PluginQuickStart
            ├── Controller
            │   └── MyController.php
            ├── DependendencyInjection
            │   └── services.xml
            ├── Resources
            │   ├── config
            │   │   └── routes.xml
            │   └── config.xml
            ├── Service
            │   └──  MyService.php
            ├── Subscriber
            │   └── MySubscriber.php
            ├── composer.json
            └── PluginQuickStart.php
```

## Plugin base

The corresponding plugin sources can be stored in the `/custom/plugins/<plugin-directory>` directory.
It is also possible to `require` your plugin via composer, since Shopware searches the `vendor` directory for packages with the type `shopware-platform-plugin`.

Each plugin has to come with a base class, which serves as an entry point into the system.
Just create a new directory inside of `custom/plugins` and choose a proper name for your plugin directory, in this example `PluginQuickStart` is used.
Afterwards create a new .php file inside of your plugin directory.

Note: Whatever name you choose for your base class file, it is **mandatory** to also choose the same name
for both the PHP class as well as the classes namespace.

The following example should shed some light into this matter.
Your plugins base class should look like this, if you were to name your plugin "PluginQuickStart":

```php
<?php declare(strict_types=1);

namespace PluginQuickStart;

use Shopware\Core\Framework\Plugin;

class PluginQuickStart extends Plugin
{
}
```
*PluginQuickStart/PluginQuickStart.php*

The directory structure could then look like this:

```
<project root>
└── custom
    └── plugins
        └── PluginQuickStart
            └── PluginQuickStart.php
```

# Plugin meta data

Another requirement for a working plugin is the `composer.json` file.
It contains all the necessary meta data of your plugin, e.g. the plugin version, it's supported Shopware platform versions,
the plugin description, the plugin title and many more.

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
            "name": "shopware AG"
        }
    ],
    "require": {
        "shopware/platform": "dev-master"
    },
    "extra": {
        "installer-name": "PluginQuickStart",
        "label": {
            "de_DE": "Beispiel Plugin",
            "en_GB": "Example Plugin"
        },
        "description": {
            "de_DE": "Deutsche Beschreibung des Plugins",
            "en_GB": "English description of the plugin"
        }
    }
}
```
*PluginQuickStart/composer.json*

Read [here](./050-plugin-information.md) for more information about the content of the composer.json file.

# Installing the plugin

Now, that you've created the two necessary plugin files, you're able to install the plugin.
This is done using one of the [plugin commands](./030-plugin-commands.md).

Starting in your project root directory, run the command `bin/console plugin:install --activate PluginQuickStart` to install and activate the plugin.

# Plugin configuration

When shipping a plugin to your customer, you might want to ship the plugin with some built-in configurations.
This way you can make sure your customers can configure your plugin to perfectly fit their needs.

The Shopware platform supports this out of the box by adding a `config.xml` file to a `Resources` directory inside your plugin.
Both the file naming as well as the directory naming is important here, so the autoloading of the `config.xml` file works as intended.

For this tutorial, a simple configuration containing a single text field is used:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/master/src/Core/System/SystemConfig/Schema/config.xsd">
    
    <card>
        <title>Minimal configuration</title>
        <title lang="de_DE">Minimale Konfiguration</title>
        <input-field>
            <name>example</name>
            <label>Example Label EN</label>
            <label lang="de_DE">Beispiel Label DE</label>
        </input-field>
    </card>
</config>
```
*PluginQuickStart/Resources/config.xml*

This configuration would now create a new text field inside a panel with the title "Minimal configuration", depending on
the language chosen.
Also, the text field's technical name is `example` in this case, and so would be the label if you didn't provide a specific label for it.
Those will be rendered into the administration settings.

For a more detailed guide on how to setup the `config.xml` and which input types exist, head over to
the detailed [plugin configuration](./070-plugin-config.md) guide.

# Listening to events via Subscriber

You registered the plugin into the Shopware platform, created a config for it and even installed and activated it afterwards.
Unfortunately, you're not really doing anything with your plugin as of yet.

One of the main purposes of a plugin is listening to several system events and then executing code once an event is dispatched.
In order to do so, the Shopware platform makes use of the [Symfony subscribers](https://symfony.com/doc/current/components/event_dispatcher.html#using-event-subscribers).

## Subscriber class

Before you can register your subscriber, you need the subscriber class itself first.
The subscriber class has to implement the `EventSubscriberInterface` and therefore it's required static method `getSubscribedEvents`.
Inside the method you'll have to return an associative array, where the key represents the event to listen to
and the actual value pointing to the method to execute once the event was dispatched.

In this example the `product.loaded` event is used, which is triggered once one or more products were loaded..
The subscriber class could be placed into a directory named `Subscriber` on the plugin root directory, the actual
naming for both the file as well as the directory is irrelevant here though.

An example of a subscriber could look like this:
```php
<?php declare(strict_types=1);

namespace PluginQuickStart\Subscriber;

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
*PluginQuickStart/Subscriber/MySubscriber.php*

The subscriber would now listen to the event `example_event` and once the event is dispatched, the method `onExampleEvent` is executed.

## The services.xml

Your subscriber now has to be registered into the [DI container](https://symfony.com/doc/current/service_container.html).
In the platform, the services in the DI container are defined in XML.

To integrate your own `services.xml` in your plugin, the `build` method of your base class has to be overwritten.
Also make sure to import all the classes necessary, just as in this example:

```php
<?php declare(strict_types=1);

namespace PluginQuickStart;

use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Config\FileLocator;

class PluginQuickStart extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('services.xml');
    }
    
}
``` 
*PluginQuickStart/PluginQuickStart.php*

This way your plugin will be looking for a file called `services.xml` inside of a `DependencyInjection` directory,
which has to be created in your plugin root directory.
The directory name `DependencyInjection` as well as the file name are **not** a requirement, just a suggestion and free for change.

## Registering your subscriber

You've created a subscriber class and you've made sure your plugin is actually looking for a `services.xml` file.
Now it's time to create the `services.xml` file itself. As mentioned above it needs to be inside of a directory called
`DependencyInjection`.

It could then contain the following XML:

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="PluginQuickStart\Subscriber\MySubscriber">
            <tag name="kernel.event_subscriber"/>
        </service>
    </services>
</container>
```
*PluginQuickStart/DependencyInjection/services.xml*

The service ID represents the fully qualified class name of your subscriber class.
If you chose another name for the `Subscriber` directory, make sure to change it here as well.

This way you've not only registered your own service via the `<service>` element, you also made sure it gets tagged
with the `kernel.event_subscriber` tag, which is the Symfony default tag for subscribers.

Your subscriber is now fully integrated:
- The subscriber class exists and it listens to an event
- The subscriber is mentioned in the `services.xml` file
- The plugin is loading the custom `services.xml` file

# Creating a controller

Another common thing to be done in a plugin is registering a custom controller, e.g. to be used as a new custom API endpoint.
For this case you could create a new directory called `Controller`, but once again, the naming and structure can be freely chosen here.

Inside this directory, just create a new PHP class for the controller, in this example it's named `MyController`.
The controller should extend from the Symfony `AbstractController` class, since it provides common features needed in the
controller context.

An example of the `MyController` controller could look like this:
```PHP
<?php declare(strict_types=1);

namespace PluginQuickStart\Controller;

use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

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
*PluginQuickStart/Controller/MyController.php*

Important to notice is the `@Route` annotation above the `myFirstApi`, which basically defines
the actual route to access the controller.

Additional to that, the Shopware platform needs to learn where to search for your controller in the first place.
This is done by adding a `routes.xml` file into a `Resources/config/` directory.
The naming for the directory is not only suggested, it is highly recommended here.
With this way, the Shopware platform finds the `routes.xml` automatically.
This is done by looking for any .xml file, whose path contains `routes`, so the path `Resources/config/routes/example.xml` would also work.

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
*PluginQuickStart/Resources/config/routes.xml*

Since the Shopware platform uses Symfony fullstack, it's also supporting configuration files written in YML or PHP. 
An representation of the same routes configuration using a YAML or PHP file instead, can be found in the Symfony documentation
about [external routing resources](https://symfony.com/doc/current/routing/external_resources.html).

Now the controller should be fully working and accessible using the route mentioned in the method's `@Route` annotation.
Since we've created an API route here, an authorization token is still necessary to actually access our controller.
Remove the 'api' from the route to circumvent the authorization for testing purposes or get more into how the Shopware platform API works [here](../../3-api/__categoryInfo.md). 

# Creating a service

Since you don't want any business logic to be executed inside of a controller or subscriber, you usually want to put your business logic
into services.
Earlier in this tutorial you already created a `services.xml` file, and as the name suggests, you can also define new services in there.

Sticking to the previous examples, you could just name it `MyService` and place it into a `Service` directory.

```php
<?php declare(strict_types = 1);

namespace PluginQuickStart\Service;

class MyService
{
    public function doSomething(): void
    {
    }
}
```
*PluginQuickStart/Service/MyService.php*

In order to access it via the [DI container](https://symfony.com/doc/current/service_container.html), you need to mention it in the said `services.xml` file:

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="PluginQuickStart\Subscriber\MySubscriber">
            <tag name="kernel.event_subscriber"/>
        </service>
        
        <service id="PluginQuickStart\Service\MyService" />
    </services>
</container>
```
*PluginQuickStart/DependencyInjection/services.xml*

Your service is now part of the DI container, but only as a [private service](https://symfony.com/doc/current/service_container/alias_private.html).
An example usage would be in your new controller.

```PHP
<?php declare(strict_types=1);

namespace PluginQuickStart\Controller;

use PluginQuickStart\Service\MyService;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

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
        <service id="PluginQuickStart\Subscriber\MySubscriber">
            <tag name="kernel.event_subscriber"/>
        </service>

        <service id="PluginQuickStart\Service\MyService" />

        <service id="PluginQuickStart\Controller\MyController" public="true">
            <argument type="service" id="PluginQuickStart\Service\MyService" />
        </service>
    </services>
</container>
```
