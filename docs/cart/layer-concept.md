# Cart layer concept

In shopware we implemented different layers for the cart process. These layers has different responsibilty and performance costs.

## Cart container
The first layer is the `\Shopware\Cart\Cart\CartContainer`. 
This layer can be viewed as a shopping list. Here, you only define which elements are to be calculated and validated later.
At this time, neither taxes, prices, or availability are of interest or anyway considered.

```php
$container = CartContainer::createNew('my_cart');
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
This layer can be viewed as a receipt. You have prices, taxes, total amounts, order numbers and labels for positions.

```php
$calculator = $this->get('cart.calculator');
$context = $this->get('shopware.storefront.context.storefront_context_service')->getShopContext();

$cart  = $calculator->calculate($container, $context);

$product = $cart->getCalculatedLineItems()->get('SW1000');
$product->getPrice();
```
*Example to get the calculated product*

## View cart
The last layer is the `\Shopware\CartBridge\View\ViewCart`. Only a single label and a order number is not enough to display a cart in an online shop checkout.
A customer wants to see images, extended descriptions, variant informations or a link to the product (in case the line item is a product). This data is not required
to calculate a cart or save an order or start a payment process, but at least this data is required. 

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
3. The cart container allows fast operations without prevalidating data before a product can be added to the cart.
4. It is possible to implement different cache layers like, **calculated cart = live** and **viewcart = cached**