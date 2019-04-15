[titleEn]: <>(Line item)
[titleDe]: <>(Line item)
[wikiUrl]: <>(../checkout/line-item?category=shopware-platform-en/checkout)

Every position in the cart is called `line item`. A line item can be a product, voucher, surcharge, bundle 
or any other type you might implement.

```php
<?php
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\Cart\ProductCollector;

$product = new LineItem('407f9c24dd414da485501085e3ead678', ProductCollector::LINE_ITEM_TYPE, 5);
```
Example of a line item of type `product`  

A line item can have multiple children, which again can have multiple children.

## Line item class
```php
<?php

namespace Shopware\Core\Checkout\Cart\LineItem;

use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Struct;

class LineItem extends Struct
{
    public const GOODS_PRIORITY = 100;

    public const VOUCHER_PRIORITY = 50;

    public const DISCOUNT_PRIORITY = 25;

    /**
     * @var string
     */
    protected $key;

    /**
     * @var string|null
     */
    protected $label;

    /**
     * @var int
     */
    protected $quantity;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var array
     */
    protected $payload;

    /**
     * @var PriceDefinitionInterface|null
     */
    protected $priceDefinition;

    /**
     * @var Price|null
     */
    protected $price;

    /**
     * @var bool
     */
    protected $good = true;

    /**
     * @var int
     */
    protected $priority = self::GOODS_PRIORITY;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var null|MediaEntity
     */
    protected $cover;

    /**
     * @var DeliveryInformation|null
     */
    protected $deliveryInformation;

    /**
     * @var LineItemCollection
     */
    protected $children;

    /**
     * @var Rule|null
     */
    protected $requirement;

    /**
     * @var bool
     */
    protected $removable = false;

    /**
     * @var bool
     */
    protected $stackable = false;

    /**
     * @throws InvalidQuantityException
     */
    public function __construct(string $key, string $type, int $quantity = 1, int $priority = self::GOODS_PRIORITY)
    {
        $this->key = $key;
        $this->type = $type;
        $this->priority = $priority;
        $this->children = new LineItemCollection();

        if ($quantity < 1) {
            throw new InvalidQuantityException($quantity);
        }
        $this->quantity = $quantity;
    }

}
```

## Line item properties
You find a list of line item properties below.

### Quantity
A quantity is always required and must be a whole number greater than zero.

### Type
Line items always have a type. The type defines how a line item is processed. 
When creating a custom line item, please choose a unique type name and allow other developers to use it 
e.g. by exposing it via a public constant.

There are predefined types:

- products (only use if the line item key correlates with an entity in the product table) -> `product`
- discount-surcharge

If you use the `product` type, Shopware will try to retrieve the product information from the database 
and enrich your line item with details and prices.

### Key and payload
Each line item has a key. The key is used to identify the line-item. The key can be any alphanumeric string.
If you do not plan to add the same line item multiple times, you can use the 
UUID of the product, voucher, discount or your customer line item.

Since two line items can have different UUIDs but reference the same product, voucher, 
discount or custom line item, you must also define the payload and set the key property.

It's also possible to add custom payload properties. 
Please be aware that you must only use alphanumeric characters.

### Label
When creating a line item, the label is not required. Please add the label if you already know it.
If you do not add a label initially, you must add it during the enrichtment process.

### Price definition
A price definition contains all required information to (re-)calculate the price of the line item.
Always add a price definition.

### CalculatedPrice
A price defines the calculated price of the line item based on quantity, taxes and other rules.
The price is not required. Shopware will automatically calculate the price based on your price definition.

### Good
Boolean value which defines if the line item is a good. Set to true by default.

### Description
Optional description of the line item.

### Cover
Optional `Shopware\Core\Content\Media\MediaEntity` to define a cover picture.

### Delivery information
Optional `Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation` to define delivery dates, 
weight and stock for a line item.

### Removable
Boolean value which defines if a line item can be removed from the cart. Set to false by default.

### Stackable
Boolean value which defines if the quantity of a line item can be changed. Set to false by default.

### Priority
A line item always has a defined priority. The priority is used when calculating the cart. 

Example: If you have a 10% discount line item for all goods in your cart, 
all goods have to be calculated before you can calculate the exact amount of the voucher.

The lower the priority, the later the line item will be processed. 
There are a few default priorities defined in the LineItem class:

Goods -> 100
Voucher -> 50
Discount -> 25

Please choose the priority of customer line items carefully and 
do not just always use the lowest/highest priority.

## Cart service

To simplify the cart process, Shopware has the `Shopware\Core\Checkout\Cart\SalesChannel\CartService`
which contains a collection of common cart operations.

### Create a new cart

```php
<?php

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\CheckoutContext;

/**
 * @Route("/", name="cart.test")
 */
public function createNewCart(CheckoutContext $checkoutContext)
{
    /** @var CartService $cartService */
    $cart = $cartService->createNew($checkoutContext);
}
```

### Get current cart

```php
<?php

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\CheckoutContext;

/**
 * @Route("/", name="cart.test")
 */
public function getCart(CheckoutContext $checkoutContext)
{
    // if not cart exists, a new one will be created
    /** @var CartService $cartService */
    $cart = $cartService->getCart($checkoutContext);
}
```

### Add a line item to cart

```php
<?php

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\CheckoutContext;
use Shopware\Core\Content\Product\Cart\ProductCollector;


/**
 * @Route("/", name="cart.test")
 */
public function addLineItem(CheckoutContext $checkoutContext)
{
    $product = new LineItem('407f9c24dd414da485501085e3ead678', ProductCollector::LINE_ITEM_TYPE, 5);
    
    /** @var CartService $cartService */
    $cartService->add($product, $checkoutContext);
}
```

Note: The add method of the cart service always triggers a recalculation of the cart.
If you add multiple line items, please consider the following code example:

```php
<?php

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\CheckoutContext;
use Shopware\Core\Content\Product\Cart\ProductCollector;


/**
 * @Route("/", name="cart.test")
 */
public function addMultipleLineItems(CheckoutContext $checkoutContext)
{
    $product        = new LineItem('407f9c24dd414da485501085e3ead678', ProductCollector::LINE_ITEM_TYPE, 5);
    $anotherProduct = new LineItem('43ab6d2834fc49e387ca089d537d6e39', ProductCollector::LINE_ITEM_TYPE, 1);
    
    /** @var CartService $cartService */
    $cartService->fill(new LineItemCollection([$product, $anotherProduct]), $checkoutContext);
}
```

### Change line item quantity

```php
<?php

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\CheckoutContext;


/**
 * @Route("/", name="cart.test")
 */
public function changeLineItemQuantity(CheckoutContext $checkoutContext)
{
    /** @var CartService $cartService */
    $cart = $cartService->getCart($checkoutContext);
    
    $cartService->changeQuantity('407f9c24dd414da485501085e3ead678', 9, $checkoutContext);
}
```

### Remove line item

```php
<?php

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\CheckoutContext;


/**
 * @Route("/", name="cart.test")
 */
public function removeLineItem(CheckoutContext $checkoutContext)
{
    /** @var CartService $cartService */
    $cart = $cartService->getCart($checkoutContext);
    
    $cartService->remove('407f9c24dd414da485501085e3ead678', $checkoutContext);
}
```

### Get deliveries

```php
<?php

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\CheckoutContext;


/**
 * @Route("/", name="cart.test")
 */
public function getDeliveries(CheckoutContext $checkoutContext)
{
    /** @var CartService $cartService */
    $cart = $cartService->getCart($checkoutContext);
    
    $deliveries = $cart->getDeliveries();
}
```

### Order

```php
<?php

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\CheckoutContext;


/**
 * @Route("/", name="cart.test")
 */
public function order(CheckoutContext $checkoutContext)
{
    /** @var CartService $cartService */
    $cartService->order($checkoutContext);
}
```
