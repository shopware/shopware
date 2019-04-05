[titleEn]: <>(Decorating a service)

## Overview

Decorating a service with your plugin is as simple as it is in Symfony.
Make sure to have a look at the [Symfony guide about decorating services](https://symfony.com/doc/current/service_container/service_decoration.html).

The only difference here is, that you need to let the Shopware platform know about your plugin's custom `services.xml` location.

This is done in the plugin's base class.

## Plugin base class

You need to overwrite the base class' `build` method to load your `services.xml` file.

```php
<?php declare(strict_types=1);

namespace ServiceDecoration;

use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Config\FileLocator;

class ServiceDecoration extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('services.xml');
    }
}
```

It now tries to load the `services.xml` file inside the `<plugin-root>/DependencyInjection` directory.

From here on, everything works exactly like in Symfony itself.

Here's an example `services.xml`:

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="ServiceDecoration\Service\MyService" />

        <service id="ServiceDecoration\Service\DecoratedService" decorates="ServiceDecoration\Service\MyService">
            <argument type="service" id="ServiceDecoration\Service\MyService.inner" />
        </service>
    </services>
</container>
```

And the related example services:
```php
<?php declare(strict_types = 1);

namespace ServiceDecoration\Service;

class MyService implements MyServiceInterface
{
    public function doSomething(): void
    {
    }
}
```

```php
<?php declare(strict_types=1);

namespace ServiceDecoration\Service;

class DecoratedService
{
    /**
     * @var MyServiceInterface
     */
    private $coreService;

    public function __construct(MyServiceInterface $myService)
    {
        $this->coreService = $myService;
    }
}

```

Note: It's **highly recommended** to work with interfaces when using the decoration pattern.