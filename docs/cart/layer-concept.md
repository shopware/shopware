# Cart layer concept

With regards to performance and seperation of responsibilities we introduced a three-layer-architecture for the cart. Each layer serves a new purpose, while the level of abstraction decreases from inside to outside.

## Cart container
The first and innermost layer is the `\Shopware\Cart\Cart\Cart`. 
This layer can be viewed as a shopping list. Here, you only define which elements are to be calculated and validated later.
At this time, neither taxes, prices, or availability are of interest or anyway considered.

```php
$container = Cart::createNew('my_cart');
$container->getLineItems()->add(
    new LineItem(
        $identifier = 'SW1000',
        ProductProcessor::TYPE_PRODUCT,
        $quantity = 5
    )
);
```
*Example to add a product to the cart*


## Calculated cart
After the shopping list is defined, this shopping list will be calculated by the `\Shopware\Cart\Cart\CartCalculator`.
At the end of the calculation process the cart calculator returns a `\Shopware\Cart\Cart\CalculatedCart`. This layer
contains delivery information, prices, taxes and availability of line items. Deliveries have already been determined and subsequently calculated.
This layer can be viewed as a receipt. It provides prices, taxes, total amounts, order numbers and labels for positions.

```php
$calculator = $this->get('cart.calculator');
$context = $this->get('shopware.storefront.context.storefront_context_service')->getShopContext();

$cart  = $calculator->calculate($container, $context);

$product = $cart->getCalculatedLineItems()->get('SW1000');
$product->getPrice();
```
*Example to get the calculated product*

## View cart
The outer layer is the `\Shopware\CartBridge\View\ViewCart`. We introduced this layer to populate the rather abstract representation of the calculated cart with view-relevant data (e.g. images, description, link to a detail page depending on the type of line item), which is not required for the calculation.

```php
$transformer = $this->get('shopware.cart.view.cart_transformer');
$view = $transformer->transform($cart, $context);

$viewProduct = $view->getViewLineItems()->get('SW1000');
$viewProduct->getCover();
```
*Example to get view data of a product*


## Conclusion

This layer concept shows different benefits:

1. The cart calculation is separated from the store front of an online shop 
2. View required data can only be fetched if required.
3. The slim cart container allows fast operations without prevalidating data before a product can be added to the cart.
4. It is possible to implement different cache layers like, **calculated cart = live** and **viewcart = cached**