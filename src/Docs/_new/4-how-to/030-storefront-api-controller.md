[titleEn]: <>(Storefront API controller)
[wikiUrl]: <>(../how-to/storefront-api-controller?category=platform-en/how-to)

This HowTo will give you a brief introduction on how to setup a custom Storefront API controller with your plugin.
Read [here](../../070-storefront-api.md) for more information about the Storefront API.
Also, [this](./020-api-controller.md) guide covers the same subject for the core API.

## Plugin base class

You don't have to override any method in the plugin's base class for this subject.

```php
<?php declare(strict_types=1);

namespace StorefrontApiController;

use Shopware\Core\Framework\Plugin;

class StorefrontApiController extends Plugin
{
}
```

## Loading the controllers via routes.xml

The `routes.xml` file is necessary to introduce your controllers to the Shopware platform.
The Shopware platform automatically searches for an `xml` file in a `Resources` folder, whose path contains `routes`.
Therefore possible default locations would be:
- <plugin-root>/Resources/**routes**.xml
- <plugin-root>/Resources/**routes**/my_controller.xml

Since only a single `xml` file is necessary for this example, the file is called `routes.xml` and will be put
into the `<plugin root>/Resources` directory.

It only has to contain the path to the plugins controllers.
This example will have it's API controller inside a `Controller` folder.

```xml
<?xml version="1.0" encoding="UTF-8" ?>

<routes xmlns="http://symfony.com/schema/routing"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://symfony.com/schema/routing
        http://symfony.com/schema/routing/routing-1.0.xsd">

    <import resource="../Controller" type="annotation" />
</routes>
```

## The controller class

Next you create a folder `Controller` inside your plugin root and in there you create a new `php` file for your actual controller.

Here's an example of what the controller could then look like:
```php
<?php declare(strict_types=1);

namespace StorefrontApiController\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class MyController extends AbstractController
{
    /**
     * @Route("/storefront-api/v1/swag/my-storefront-api-action", name="storefront-api.action.swag.my-storefront-api-action", methods={"GET"})
     */
    public function myFirstApi(): JsonResponse
    {
        return new JsonResponse(['You successfully created your first storefront api controller route']);
    }
}
```

There are several things to note about the `@Route` annotation:
- In order for your controller to be an Storefront API controller, your route needs to start with `/storefront-api/`
- The respective method only supports `GET` requests, hence the `methods={"GET"}` part of the annotation
- Make sure to use your vendor prefix (`swag` in this example), so route collisions with other plugins won't be an issue