[titleEn]: <>(Custom API routes)
[wikiUrl]: <>(../plugin-system/custom-api-routes?category=shopware-platform-en/plugin-system)

Defining custom `API routes` in `Shopware` is straightforward and goes analogous to [Symfony - Routing](https://symfony.com/doc/current/routing.html).
At the end of this guide, you find the full example as download.

## Overview
```
└── plugins
    └── SwagExample
        ├── Controller
        │   └── YourFirstApiController.php
        ├── Resources
        │   └── routes.yaml
        └── SwagExample.php
```
*File structure*
## Plugin Base Class
For this plugin you only need a minimalistic base class, which allows you to install the plugin:

```php
<?php declare(strict_types=1);

namespace SwagExample;

use Shopware\Core\Framework\Plugin;

class SwagExample extends Plugin
{

}
```

## API Controller
The most basic API Controller could look something like this:

```PHP
<?php declare(strict_types=1);

namespace SwagExample\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class YourFirstApiController extends AbstractController
{
    /**
     * @Route("/api/v{version}/_action/swag/my-api-action", name="api.action.swag.my-api-action", methods={"POST"})
     */
    public function yourFirstApiAction(): JsonResponse
    {
        return new JsonResponse(['You successfully created your first API route']);
    }
}
```
*Controller/YourFirstApiController.php*

Below you can find parts of the route definition and what they mean.

| Route Definition Snippet | Meaning                                                                                                                                                                   |
|--------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| @Route(...)              | Defines a new [Symfony - Annotation Route](https://symfony.com/doc/current/routing.html)                                                                                  |
| /api/v{version}          | Your route belongs to the versioned [Shopware - Administration API](../1-getting-started/20-getting-started.md#using-the-api) and is secured via the default authorization|
| /_action                 | Standard `Shopware` prefix for custom API actions                                                                                                                         |
| /swag                    | Your plugin/company identifier                                                                                                                                            |
| my-api-action            | Your API action name                                                                                                                                                      |

Below you can find parts of your route name and what they mean.

| Name Snippet   | Meaning                                 |
|----------------|-----------------------------------------|
| api.action     | `Shopware` standard for API actions     |
| .swag          | Your plugin/company identifier          |
| .my-api-action  | Your API action name                   |

## Route Configuration
The next you need to do is make your routes known to `Symfony`. For that, you will need a route configuration file.
Per default, `Shopware` searches for this file in the `Resources` folder.

```yaml
swag:
  resource: ../Controller/
  type: annotation
```
*Resources/routes.yaml*

With this route configuration file, you tell Symfony to load all annotation routes from the Controller folder.
Alternatively, you could also define your routes directly in the route configuration file like this:

```yaml
my-api-action:
  path: /api/v{version}/_action/swag/my-api-action
  controller: SwagExample\Controller\YourFirstApiController::yourFirstApiAction
  methods: POST
```
*Resources/routes.yaml*

Both ways are legit, but you should use annotation routes because they are easier to keep track of.
That is all you need to create your custom API route, go ahead and give it a try.

## Download
Here you can *Download Link Here* the plugin.