[titleEn]: <>(SalesChannel-API controller)
[metaDescriptionEn]: <>(This HowTo will give you a brief introduction on how to setup a custom SalesChannel-API controller with your plugin.)
[hash]: <>(article:how_to_sales_channel_api_controller)

This HowTo will give you a brief introduction on how to setup a custom SalesChannel-API controller with your plugin.
Read [here](./../45-store-api-guide/__categoryInfo.md) for more information about the SalesChannel-API.
Also, [this](./020-api-controller.md) guide covers the same subject for the Admin API.

## Plugin base class

You don't have to override any method in the plugin's base class for this subject.

```php
<?php declare(strict_types=1);

namespace Swag\SalesChannelApiController;

use Shopware\Core\Framework\Plugin;

class SalesChannelApiController extends Plugin
{
}
```

## Loading the controllers via routes.xml

The `routes.xml` file is necessary to introduce your controllers to Shopware 6.
Shopware 6 automatically searches for an `xml` file in a `src/Resources/config/` directory, whose path contains `routes`.
Therefore possible default locations would be:
- <plugin-root>/src/Resources/config/**routes**.xml
- <plugin-root>/src/Resources/config/**routes**/my_controller.xml

Since only a single `xml` file is necessary for this example, the file is called `routes.xml` and will be put
into the `<plugin root>/src/Resources/config` directory.

It only has to contain the path to the plugins controllers.
This example will have its API controller inside a `Controller` folder.

```xml
<?xml version="1.0" encoding="UTF-8" ?>

<routes xmlns="http://symfony.com/schema/routing"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://symfony.com/schema/routing
        http://symfony.com/schema/routing/routing-1.0.xsd">

    <import resource="../../Controller" type="annotation" />
</routes>
```

## The controller class

Next you create a directory `src/Controller` inside your plugin root and in there you create a new `php` file for your actual controller.

Here's an example of what the controller could then look like:
```php
<?php declare(strict_types=1);

namespace Swag\SalesChannelApiController\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class MyController extends AbstractController
{
    /**
     * @RouteScope(scopes={"sales-channel-api"})
     * @Route("/sales-channel-api/v3/swag/my-sales-channel-api-action", name="sales-channel-api.action.swag.my-sales-channel-api-action", methods={"GET"})
     */
    public function myFirstApi(): JsonResponse
    {
        return new JsonResponse(['You successfully created your first SalesChannel-API controller route']);
    }
}
```

There are several things to note about the `@Route` annotation:
- In order for your controller to be an SalesChannel-API controller, your route needs to start with `/sales-channel-api/`
- The respective method only supports `GET` requests, hence the `methods={"GET"}` part of the annotation
- Make sure to use your vendor prefix (`swag` in this example), so route collisions with other plugins won't be an issue

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-storefront-api-controller).
