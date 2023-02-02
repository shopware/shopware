[titleEn]: <>(Custom storefront controller)
[metaDescriptionEn]: <>(This HowTo will give an example on writing and calling your own storefront controller.)
[hash]: <>(article:how_to_storefront_controller)

## Overview

If you want to call some custom business logic from your template you will need to write your own storefront controller.
This HowTo will show you how to achieve this, by writing an controller that clears the cart of the user.
You will hook this controller action to a button on the cart overview page.

## Creating a route

Create a class inside the `<plugin base>/src/Storefront/Controller` and name it `ClearCartController`.
As this controller should be accessible through the storefront extend from the abstract StorefrontController. 
You need a own route for every controller action, you will use Symfony's `@Route` annotation for this:

```php
<?php declare(strict_types=1);

namespace Swag\StorefrontController\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class ClearCartController extends StorefrontController
{
    /**
     * @Route("/cart/clear", name="frontend.checkout.clearCart", options={"seo"="false"}, methods={"GET"})
     */
    public function clearCart(SalesChannelContext $context)
    {
    }
}
```

Give the Route an URL ("/cart/clear" in this case), a name and define over which HTTP-Verbs this route is reachable.
Also define that there are no seo-urls for this route.

Next you have to tell Symfony that it should search for routes in your /Controller folder.
Therefore add an `routes.xml` inside the `<plugin base>/src/Resources/config` folder.

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<routes xmlns="http://symfony.com/schema/routing"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://symfony.com/schema/routing
        https://symfony.com/schema/routing/routing-1.0.xsd">

    <import resource="../../**/Storefront/Controller/*Controller.php" type="annotation" />
</routes>
```

With this code you tell Symfony that it should look in your /Controller folder and check every file ending with `Controller.php` whether they define any routes.

## Building your custom logic

Next you will build the functionality to clear the cart.
Use the cart service to load the cart associated with the token from the context and then iterate over all lineItems inside the cart and remove them from the cart.
Your controller now looks like this:

```php
<?php declare(strict_types=1);

namespace Swag\StorefrontController\Storefront\Controller;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class ClearCartController extends StorefrontController
{
    /**
     * @var CartService
     */
    private $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * @Route("/cart/clear", name="frontend.checkout.clearCart", options={"seo"="false"}, methods={"GET"})
     */
    public function clearCart(SalesChannelContext $context)
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);

        foreach ($cart->getLineItems() as $lineItem) {
            $this->cartService->remove($cart, $lineItem->getId(), $context);
        }
    }
}
```

Now you need to register your controller in the DI-Container, therefore edit the `Resources/config/services.xml` to look like this:

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Swag\StorefrontController\Storefront\Controller\ClearCartController" public="true">
            <argument type="service" id="Shopware\Core\Checkout\Cart\SalesChannel\CartService"/>

            <call method="setContainer">
                <argument type="service" id="service_container"/>
            </call>
        </service>
    </services>
</container>
```

## Rendering Templates

Lastly your Controller Action needs to return a Response object. You can render an own template via `$this->renderStorefront()` or forward to another action.
In your case you want to reload the cart page to indicate the user that the action was successful, since the cart should be empty now:

```php
    /**
     * @Route("/cart/clear", name="frontend.checkout.clearCart", options={"seo"="false"}, methods={"GET"})
     */
    public function clearCart(SalesChannelContext $context)
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);

        foreach ($cart->getLineItems() as $lineItem) {
            $this->cartService->remove($cart, $lineItem->getId(), $context);
        }

        return $this->forwardToRoute('frontend.checkout.cart.page');
    }
```

## Triggering your controller

Now you need to trigger your custom action somehow.
Extend the cart page template and add a anchor element that has an href to your route:

```twig
{% sw_extends '@Storefront/storefront/page/checkout/cart/index.html.twig' %}

{% block page_checkout_cart_product_table %}
    {{ parent() }}

    <div class="row col-md-2">
        <a class="btn btn-primary btn-block" href={{ path('frontend.checkout.clearCart') }}>
            Clear cart
        </a>
    </div>
{% endblock %}
```

For more information on how to extend storefront templates, take a look [here](./250-extending-storefront-block.md).

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-storefront-controller).


