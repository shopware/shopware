[titleEn]: <>(Creating a service)
[metaDescriptionEn]: <>(Creating custom services for your plugin is as simple as it is in Symfony bundles, since Shopware 6 plugins are basically just extended Symfony bundles. This HowTo will cover that subject in short anyway.)
[hash]: <>(article:how_to_service)

## Overview

Creating custom services for your plugin is as simple as it is in Symfony bundles, since
Shopware 6 plugins are basically just extended Symfony bundles.
Make sure to have a look at the [Symfony documentation](https://symfony.com/doc/current/service_container.html#creating-configuring-services-in-the-container), to find out how services are registered in Symfony itself.

## Registering your service

The main requirement here is to have a `services.xml` file placed into `Resources/config/` folder.
The services are automatically registered via autoloading.

From here on, everything works exactly like in Symfony itself.

Here's an example `services.xml`:

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Swag\CustomService\Service\MyService" />
    </services>
</container>
```

And the related example service:
```php
<?php declare(strict_types=1);

namespace Swag\CustomService\Service;

class MyService
{
    public function doSomething(): void
    {
    }
}
```

*Note: By default, all services in Shopware 6 are marked as `private`.*
Read more about private and public services [here](https://symfony.com/doc/current/service_container/alias_private.html#marking-services-as-public-private).

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-custom-service).
