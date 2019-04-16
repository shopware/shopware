[titleEn]: <>(API controller)
[metaDescriptionEn]: <>(This HowTo will give you a brief introduction on how to setup a custom management API controller with your plugin.)

This HowTo will give you a brief introduction on how to setup a custom management API controller with your plugin.
Read [here](./../3-api/10-management-api.md) for more information about the management API itself.

## Plugin base class

The plugin's base class does not have to overwrite any method just for this objective.

```php
<?php declare(strict_types=1);

namespace Swag\ApiController;

use Shopware\Core\Framework\Plugin;

class ApiController extends Plugin
{
}
```

## Loading the controllers via routes.xml

The `routes.xml` file is necessary to introduce our controllers to the Shopware platform.
The Shopware platform automatically searches for an `xml` / `yml` / `php` file in a `src/Resources/config/` directory, whose path contains `routes`.
In this example, only `xml` is used.
Therefore possible default locations would be:
- <plugin-root>/src/Resources/config/**routes**.xml
- <plugin-root>/src/Resources/config/**routes**/my_controller.xml

Since only a single `xml` file is necessary for this example, the file is called `routes.xml` and will be put
into the `<plugin root>/src/Resources/config/` directory.

It only has to contain the path to the controllers, that should be known to the Shopware platform.
This example will have it's API controller inside a `Controller` directory.

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
Controllers contain actions that handle requests and return responses.

Here's an example of what the controller could then look like:
```php
<?php declare(strict_types=1);

namespace Swag\ApiController\Controller;

use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class MyController extends AbstractController
{
    /**
     * @Route("/api/v{version}/swag/my-api-action", name="api.action.swag.my-api-action", methods={"GET"})
     */
    public function myFirstApi(Request $request, Context $context): JsonResponse
    {
        return new JsonResponse(['You successfully created your first controller route']);
    }
}
```

There are several things to note about the `@Route` annotation:
- In order for your controller to be an API controller, your route needs to start with `/api/`
- The respective method only supports `GET` requests, hence the `methods={"GET"}` part of the annotation
- Make sure to use your vendor prefix (`swag` in this example), so route collisions with other plugins won't be an issue

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-api-controller).