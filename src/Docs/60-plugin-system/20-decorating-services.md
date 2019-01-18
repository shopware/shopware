[titleEn]: <>(Decorating Services)
[wikiUrl]: <>(../plugin-system/decorating-services?category=shopware-platform-en/plugin-system)

Decorating `Services` in Shopware is straightforward and goes analogous to [Symfony - How to Decorate Services](https://symfony.com/doc/current/service_container/service_decoration.html).
In this guide, you are going to create a plugin that will prevent guests from viewing product detail pages.
At the end of this guide, you'll find the full example as download.

## Overview
Below you can see the file structure, that you are going to create during this guide.

```
└── plugins
    └── SwagExample
        ├── Decorator
        │   └── DetailControllerDecorator.php
        ├── DependencyInjection
        │   └── decorator_definition.xml
        └── SwagExample.php
```
*Plugin structure*

## Service Definition
First, you should create your service XML file:

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="SwagExample\Decorator\DetailControllerDecorator"
                 decorates="Shopware\Storefront\Product\Controller\DetailController">
            <argument type="service" id="SwagExample\Decorator\DetailControllerDecorator.inner"/>
        </service>
    </services>
</container>
```
*DependencyInjection/decorator_definition.xml*

In this file, you define a new service to decorate the Shopware `DetailController` and pass the original controller
as an argument to your service. This way you can use the original functionality in your service, without recreating it.

## Plugin Base Class
In your plugin base class you load your created service XML file `DependencyInjection/decorator_definition.xml`:

```php
<?php declare(strict_types=1);

namespace SwagExample;

use Shopware\Core\Framework\Plugin;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class SwagExample extends Plugin
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $loader = new XmlFileLoader($container, new FileLocator([__DIR__ . '/DependencyInjection']));
        $loader->load('decorator_definition.xml');
    }
}
```
*SwagExample.php*

## Decorator
All that's left now is the Decorator itself:

```php
<?php declare(strict_types=1);

namespace SwagExample\Decorator;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Product\Controller\DetailController;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DetailControllerDecorator extends AbstractController
{
    /**
     * @var DetailController
     */
    private $originalController;

    public function __construct(DetailController $controller)
    {
        $this->originalController = $controller;
    }

    public function index(string $id, CheckoutContext $context, Request $request): Response
    {
        if ($context->getCustomer()) {
            return $this->originalController->index($id, $context, $request);
        }

        return new RedirectResponse($this->generateUrl('frontend.account.login.page', ['redirectTo' => $request->getRequestUri()]));
    }
}
```
*Decorator/DetailControllerDecorator.php*

To understand what you need to do, just take a look into the `Shopware\Storefront\PageController\DetailController`.
The `DetailController` contains one `index` method, which returns a `Response` object.
If the user is logged in, you want to call the original `index` method, to display the product detail page.
You can achieve that by checking if the [context](../1-getting-started/20-getting-started.md#context) holds user information.
Otherwise, return a new `RedirectResponse`, which leads to the login page.
Because you inherited from `AbstractController`, you can generate the URL based on the route's name, by calling `$this->generateUrl()`.
To find the route's name, just take a look into the `Shopware\Storefront\PageController\AccountController`,
there you will find the `login` method with the route name `frontend.account.login.page`. Also, you should see that an argument `redirectTo` is passed to the `renderStorefront` method. If you append the `RequestUri` as a get parameter to the URL,
the user gets redirected back to the product detail page he wanted to visit in the first place.


## Download
Here you can *Download Link Here* the Plugin.