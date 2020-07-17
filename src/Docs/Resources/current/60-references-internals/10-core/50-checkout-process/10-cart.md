[titleEn]: <>(Cart)
[hash]: <>(article:checkout_cart)

Repository Link: [`https://github.com/shopware/platform/tree/master/src/Core/Checkout/Cart`](https://github.com/shopware/platform/tree/master/src/Core/Checkout/Cart)

Shopping cart management is a central feature of Shopware 6. The shopping cart resides in the checkout bundle and is a central part of the checkout process.

## Design goals

The cart was designed with a few design goals in mind.

Adaptability
  : Although many *services* exist to make working with the cart simple and intuitive, the cart itself can be changed through many different processes and adapt to many different use cases.
  
Performance
  : With the *no waste* philosophy the cart is designed by identifying key processes and optimizing upon them. Therefore the amount of calculations, queries and iterations is kept to a minimum and a clear state management is implemented.

Independence
  : The cart is independent from many core entities of Shopware 6. It does not itself know that product, surcharges or discounts exist but communicates through interfaces with its line items.

## Cart Struct

[`\Shopware\Core\Checkout\Cart\Cart`](https://github.com/shopware/platform/blob/master/src/Core/Checkout/Cart/Cart.php)

An instance of this class represents one single Cart. As you can see relations to central Entities of the System are omitted. This allows Shopware 6 to manage multiple carts per user and per SalesChannel, or one across all sales channels. The only identification is a token hash.

The diagram below illustrates the data that a cart holds.

![cart struct](./dist/cart-struct.png)

This is a highly **mutable** data structure that is acted upon from requests and calculated and validated through services. It contains:

[Line Items](https://github.com/shopware/platform/blob/master/src/Core/Checkout/Cart/LineItem/LineItem.php)
   : A line item represents an order position. It may be a *shippable* good, a download article or even a bundle of many products.
   : Line items contain properties that tell the cart how to handle changes in line items. (eg. *stackable* - quantity can be changed, *removable* - removable through the api, ...)
   : A line item is the main extension point for the cart process. Therefore a promotion, a discount or a surcharge is also a line item.
   : A line item can even contain other line items. So a single order position can be the composition of multiple single line items.
    
[Transaction](https://github.com/shopware/platform/blob/master/src/Core/Checkout/Cart/Transaction/Struct/Transaction.php)
   : A payment in the cart. Contains a payment handler and the amount.
   
[Delivery](https://github.com/shopware/platform/blob/master/src/Core/Checkout/Cart/Delivery/Struct/Delivery.php)
   : A shipment in the cart. Contains a date, a method, a target location and the line items that should be shipped together.
   
[Error](https://github.com/shopware/platform/blob/master/src/Core/Checkout/Cart/Error/Error.php)
   : Validation errors that prevent ordering that cart.

[Tax](https://github.com/shopware/platform/blob/master/src/Core/Checkout/Cart/Tax/Struct/CalculatedTax.php)
   : The calculated tax rate for the cart.

[Price](https://github.com/shopware/platform/blob/master/src/Core/Checkout/Cart/Price/Struct/CartPrice.php)
   : The price of all line items including tax, delivery costs and vouchers discounts and surcharges.
   
Shopware 6 manages the carts state through different services. The different states a cart can inhabit are illustrated in the diagram below:

![cart state](./dist/cart-state.png)
 
In the next chapters we will take a look at the **calculation** and **data enrichment**  as well as the **control** of a cart in the system.

## Calculation

Calculating a cart is one of the more costly operations a eCommerce System must support. Therefore the cut of the interfaces and the design of the process follows the **no waste** philosophy of Shopware 6 very closely. Calculation is a multi stage process that revolves around the mutation of data structure of the cart struct.

![calculation steps](./dist/calculation-steps.png)

### Cart enrichment

Enrichment secures the *Independence* and *Adaptability* of Shopware 6. Basically the Cart is able to create and contain line items that are initially empty and will only be loaded (=**enriched**) during calculation. The following code snippet illustrates this behaviour:

```php
<?php 

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;

$lineItem = new LineItem(/* ... */);
/** @var $cart Cart */
$cart->getLineItems()->add($lineItem);

$lineItem->getPrice(); // is now null
// enrich the cart
$lineItem->getPrice(); // now set up
```

This process is transparently controlled from the cart but executed through implementations of the [`\Shopware\Core\Checkout\Cart\CollectorInterface`](https://github.com/shopware/platform/blob/master/src/Core/Checkout/Cart/CollectorInterface.php). This interface is cut in order to reduce the number database calls necessary to setup the cart's data structure for **price calculation** and **inspection** (meaning: rendering in a storefront, reading from the API).

The default collectors implemented in Shopware 6:

| service id | task |
| ---------- | ---- |
| `Shopware\Core\Content\Product\Cart\ProductCollector` | enrich all referenced products |
| `Shopware\Core\Checkout\Promotion\Cart\CartPromotionsCollector` | enrich add, remove and validate promotions |
| `Shopware\Core\Checkout\Shipping\Cart\ShippingMethodPriceCollector` | handle shipping prices |

And this is the call order:

![enrichment with multiple collectors](./dist/enrichment-steps.png)

### Cart Processors - Price Calculation And Validation

The fully enriched cart is then processed. The price information for all individual `LineItems` is now set up, so the sums can be calculated. This happens in the [`\Shopware\Core\Checkout\Cart\Processor`](https://github.com/shopware/platform/blob/master/src/Core/Checkout/Cart/Processor.php).

Steps: 

* The lineItem prices by applying the quantity and the tax rate
* Sets up the deliveries and calculates costs
* Sums the different Cart prices (incl, excl. vat, inc. excl. shipping)

Afterwards the calculation of prices is done and the cart can be inspected from the rule system.

### Context Rules

The cart is then validated against the included rules which can lead to a change in the carts data, so a revalidation becomes necessary. Scenario:

Suppose you sell cars and have the following rules:

* Everybody buying a **car** gets a **pair of sunglasses** for free
* Every Cart containing **two products** gets a discount of **2%**

Let's see what happens if a car is bought:

![cart validation](./dist/cart-validation.png)

As you can see the cart is modified during the enrichment process to at first contain the sunglasses and then again to contain the discount and result in the expected state.

## Cart storage

Contrary to other entities in the System the Cart is not managed through the Data Abstraction Layer. The Cart can only be written and retrieved as a whole. This is done for one reason mainly: **The cart does only make sense as a whole**. As discussed in the sections the workload of Shopware 6 can only be performed on the whole object in memory.

## Cart Control

The state changes and cart mutation is handled automatically by a facade the [`\Shopware\Core\Checkout\Cart\SalesChannel\CartService`](https://github.com/shopware/platform/blob/master/src/Core/Checkout/Cart/SalesChannel/CartService.php). It controls, sets up and modifies the cart struct.

The Interface therefore handles alike:


#### Create a new cart

```php:./_examples/10-cart-example.php#ExampleCreateNew
```

#### Get current cart

```php:./_examples/10-cart-example.php#ExampleCurrentCart
```

#### Add a line item to cart

```php:./_examples/10-cart-example.php#ExampleAddToCart
```

*Note: The add method of the cart service always triggers a recalculation of the cart.

#### Change line item quantity

```php:./_examples/10-cart-example.php#ExampleChangeQuantity
```

#### Remove line item

```php:./_examples/10-cart-example.php#ExampleRemoveItem
```

#### Get deliveries

```php:./_examples/10-cart-example.php#ExampleGetDeliveries
```

### Order

```php:./_examples/10-cart-example.php#ExampleOrder

```
