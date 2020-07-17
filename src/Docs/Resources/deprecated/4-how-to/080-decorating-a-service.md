[titleEn]: <>(Decorating a service)
[metaDescriptionEn]: <>(The decorator pattern became more and more popular in PHP recently. This HowTo will teach you how to decorate an existing service via your plugin.)
[hash]: <>(article:how_to_service_decoration)

## Overview

Decorating a service with your plugin is as simple as it is in Symfony.
Make sure to have a look at the [Symfony guide about decorating services](https://symfony.com/doc/current/service_container/service_decoration.html).

## Decorating a service

The main requirement here is to have a `services.xml` file loaded in your plugin.
This can be achieved by placing the file into a `Resources/config` directory relative to your plugin's base class location.
Make sure to also have a look at the method [getServicesFilePath](./../2-internals/4-plugins/020-plugin-base-class.md#getServicesFilePath)

From here on, everything works exactly like in Symfony itself.

Here's an example `services.xml`:

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Swag\ServiceDecoration\Service\MyService" />

        <service id="Swag\ServiceDecoration\Service\ServiceDecorator" decorates="Swag\ServiceDecoration\Service\MyService">
            <argument type="service" id="Swag\ServiceDecoration\Service\ServiceDecorator.inner" />
        </service>
    </services>
</container>
```

And the related example services:
```php
<?php declare(strict_types=1);

namespace Swag\ServiceDecoration\Service;

class MyService implements MyServiceInterface
{
    public function doSomething(): string
    {
        return 'Did something.';
    }
}
```

```php
<?php declare(strict_types=1);

namespace Swag\ServiceDecoration\Service;

class ServiceDecorator implements MyServiceInterface
{
    /**
     * The original service which could be used in the decorator
     *
     * @var MyServiceInterface
     */
    private $decoratedService;

    public function __construct(MyServiceInterface $myService)
    {
        $this->decoratedService = $myService;
    }

    public function doSomething(): string
    {
        $originalResult = $this->decoratedService->doSomething();
        
        return $originalResult . ' Did something additionally.';
    }
}
```

Note: It's **highly recommended** to work with interfaces when using the decoration pattern.

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-service-decoration).
