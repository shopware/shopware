# Changelog

## Update (2017-10-25)
We updated the article and our code examples to match the current Shopware version.
We also created a plugin which shows how to extend the administration interface, the frontend and the cart process. When the plugin is installed, you can define a percental discount in the administration interface.
For every discounted product a badge will the be shown in the frontend.
When you add an discounted product to the cart, a voucher (representing the discount) will be added to the cart.
You can download the plugin
[here](https://github.com/shopwareLabs/SwagProductDiscount)

If you want to learn more about the cart architecture and the different pattern, take a look at our [documentation](https://github.com/shopware/shopware/tree/labs/docs).

## Update (2017-09-27)
The basic functions of the cart bundle are now implemented in Shopware's frontend.
You can add products to the cart, change their quantity, remove them from the cart and finish the order process.
The order will be persisted and is visible in the backend.
The cart widget in the top right corner now shows how many positions are in the cart and the total value.
An order confirm page shows all cart items as well as the calculated (separate) deliveries which depend on product stocks and delivery times.

# Description

## First concept
On September 12th of 2016, we released a first concept for a new cart bundle.
You can see the development process on <a href="https://github.com/shopware/shopware-cart-poc">Github</a>, where we created a new repository which allows the community to create pull requests and issues.
The new repository contains a new bundle in `/engine/Shopware/Bundle/CartBundle` which contains a first proof of concept for a new cart process.
This article documents the current implementation and how it can be used. 

## Usage

### Add an line item
Lets start with a simple example: *Add a product to cart*

```
use Shopware\Cart\LineItem\LineItem;
use Shopware\Cart\Product\ProductProcessor;
use Shopware\CartBridge\Service\StoreFrontCartService;

public function addProductAction()
{
    /** @var StoreFrontCartService $cartService */
    $cartService = $this->container->get('shopware.cart.storefront_service');

    $cartService->add(
        new LineItem(
            'SW10239', // number
            ProductProcessor::TYPE_PRODUCT,
            1 // quantity
        )
    );
}
```

### Remove a line item
Next we remove this item again using the cart identifier (see above `SW10239`)
```
use Shopware\CartBridge\Service\StoreFrontCartService;

public function removeAction()
{
    /** @var CartBundle\Infrastructure\StoreFrontCartService $cartService */
    $cartService = $this->container->get('shopware.cart.storefront_service');

    $cartService->remove('SW10239');
}
```

### Get line items
To get access of all line items in cart, the `StoreFrontCartService` allows access on the calculated cart over `getCalculated()`.

```
use Shopware\Cart\Cart\CalculatedCart;
use Shopware\Cart\LineItem\CalculatedLineItemInterface;
use Shopware\Cart\LineItem\LineItem;
use Shopware\Cart\Product\ProductProcessor;
use Shopware\Cart\Tax\CalculatedTax;
use Shopware\CartBridge\Service\StoreFrontCartService;

public function showLineItemsAction()
{
    /** @var StoreFrontCartService $cartService */
    $cartService = $this->container->get('shopware.cart.storefront_service');

    $cartService->add(
        new LineItem(
            $number = 'SW10239',
            ProductProcessor::TYPE_PRODUCT,
            $quantity = 10
        )
    );
    $cartService->add(
        new LineItem(
            $number = 'SW10009',
            ProductProcessor::TYPE_PRODUCT,
            $quantity = 10
        )
    );

    /** @var CalculatedCart $cart */
    $cart = $cartService->getCart()->getCalculatedCart();

    /** @var CalculatedLineItemInterface $lineItem */
    foreach ($cart->getCalculatedLineItems() as $lineItem) {
        echo "\n\n line item: " . $lineItem->getIdentifier();
        echo "\n unit price: " . $lineItem->getPrice()->getUnitPrice();
        echo "\n quantity: " . $lineItem->getPrice()->getQuantity();
        echo "\n price : " . $lineItem->getPrice()->getTotalPrice();
        echo "\n taxes : " . $lineItem->getPrice()->getCalculatedTaxes()->getAmount();

        /** @var CalculatedTax $tax */
        foreach ($lineItem->getPrice()->getCalculatedTaxes() as $tax) {
            echo "\n tax " . $tax->getTaxRate() . "% : " . $tax->getTax();
        }
    }
}
```

### Get cart amount
The cart amount is stored inside the `CalculatedCart` and can be accessed over `getPrice()`.

```php
use Shopware\Cart\Cart\CalculatedCart;
use Shopware\Cart\LineItem\LineItem;
use Shopware\Cart\Product\ProductProcessor;
use Shopware\Cart\Tax\CalculatedTax;
use Shopware\CartBridge\Service\StoreFrontCartService;

public function showAmountAction()
{
    /** @var StoreFrontCartService $cartService */
    $cartService = $this->container->get('shopware.cart.storefront_service');

    $cartService->add(
        new LineItem(
            $number = 'SW10239',
            ProductProcessor::TYPE_PRODUCT,
            $quantity = 10
        )
    );
    $cartService->add(
        new LineItem(
            $number = 'SW10009',
            ProductProcessor::TYPE_PRODUCT,
            $quantity = 5
        )
    );

    /** @var CalculatedCart $cart */
    $cart = $cartService->getCart()->getCalculatedCart();

    echo "\n amount: " . $cart->getPrice()->getTotalPrice();
    echo "\n amount net: " . $cart->getPrice()->getNetPrice();
    echo "\n tax amount: " . $cart->getPrice()->getCalculatedTaxes()->getAmount();

    /** @var CalculatedTax $tax */
    foreach ($cart->getPrice()->getCalculatedTaxes() as $tax) {
        echo "\n tax " . $tax->getTaxRate() . "% : " . $tax->getTax();
    }
}
```

### Get deliveries
Each cart contains a collection of deliveries, in case that the customer is logged in (requires a delivery address).
This deliveries can be accessed over `getDeliveries()`. 

```
use Shopware\Cart\Cart\CalculatedCart;
use Shopware\Cart\Delivery\Delivery;
use Shopware\Cart\Delivery\DeliveryPosition;
use Shopware\Cart\LineItem\LineItem;
use Shopware\Cart\Product\ProductProcessor;
use Shopware\CartBridge\Service\StoreFrontCartService;

public function showDeliveriesAction()
{
    /** @var StoreFrontCartService $cartService */
    $cartService = $this->container->get('shopware.cart.storefront_service');
    $cartService->add(
        new LineItem(
            $number = 'SW10239',
            ProductProcessor::TYPE_PRODUCT,
            $quantity = 50
        )
    );
    $cartService->add(
        new LineItem(
            $number = 'SW10009',
            ProductProcessor::TYPE_PRODUCT,
            $quantity = 25
        )
    );

    /** @var CalculatedCart $cart */
    $cart = $cartService->getCart()->getCalculatedCart();

    /** @var Delivery $delivery */
    foreach ($cart->getDeliveries() as $index => $delivery) {
        echo "\n\n ---------- \n delivery #" . $index;

        $price = $delivery->getPositions()->getPrices()->sum();
        echo "\n amount of delivery: " . $price->getTotalPrice();
        echo "\n tax amount of delivery: " . $price->getCalculatedTaxes()->getAmount();

        echo "\n\n address: ";
        echo ' ' . $delivery->getLocation()->getAddress()->getFirstname();
        echo ' ' . $delivery->getLocation()->getAddress()->getLastname();
        echo ' ' . $delivery->getLocation()->getAddress()->getStreet();
        echo ' ' . $delivery->getLocation()->getAddress()->getZipcode();
        echo ' ' . $delivery->getLocation()->getAddress()->getCity();

        echo "\n delivery date: ";
        echo $delivery->getDeliveryDate()->getEarliest()->format('Y-m-d');
        echo ' - ';
        echo $delivery->getDeliveryDate()->getLatest()->format('Y-m-d');

        /** @var DeliveryPosition $position */
        foreach ($delivery->getPositions() as $i => $position) {
            echo "\n\n position " . $i;
            echo "\n quantity " . $position->getQuantity();
            echo "\n price in delivery: " . $position->getPrice()->getTotalPrice();
        }

    }
```

## Architecture

### Cart layers
The cart passes through different states during the calculation process.
In order to provide a valid state for each service layer, the states are reflected in different classes:

* `Shopware\Cart\Cart\CartContainer`
    * Defines which line items have to be calculated inside the process
* `Shopware\Cart\Cart\ProcessorCart`
    * Defines which line items have already been calculated and which deliveries have been generated 
* `Shopware\Cart\Cart\CalculatedCart`
    * Contains a list of all calculated line items 
    * Contains a collection of all deliveries
    * Has a calculated price with total tax amounts, tax rules and net or gross prices
    
<img src="img/img.003.png" width="40%"/>

### Processor concept 
The following diagram shows the architecture behind the cart process for product calculation:   

![image](img/img.001.png)

The cart calculation is done in the `Shopware\Cart\Cart\CartCalculator` class.  
This class contains a list of `Shopware\Cart\Cart\CartProcessorInterface`, which are the access points for the Shopware core and third party developers in the cart process. 

```php
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

interface CartProcessorInterface
{
   public function process(
        CartContainer $cartContainer,
        ProcessorCart $processorCart,
        StructCollection $dataCollection,
        ShopContext $context
    ): void;
}

```

### Domain and Infrastructure layers
For the architecture design, we took great care to separate business logic from the database and Shopware dependencies.

That means all Shopware-specific operations such as database access, delivery information or price selections (with graduated prices or price groups) are separated into individual gateways that can be replaced with other data sources.

These layers are named **Cart** and **CartBridge** which are placed on the first level of the `src` folder.
The Cart layer should not have any dependencies to the Shopware core.
That's the infrastructure layer.
Interactions with Shopware are defined in the CartBridge.

![image](img/img.002.png)


## Price calculations
At the moment, the `Cart` contains the following calculation classes:
* `\Shopware\Cart\Price\PriceCalculator`
    * Calculates a total price for a provided `PriceDefinition`
    * Calculates the gross/net unit price and total price
    * Uses tax calculation services for including/excluding taxes

* `\Shopware\Cart\Price\PercentagePriceCalculator`
    * Calculates a percentage price based on a provided collection of prices (`PriceCollection`)
    * Sums all prices to a total amount and calculates a percentage price value
    * Calculates the percentage share of tax rules inside the provided prices and calculates the taxes percentage 
    * Example:
        * 100.00 € with 19% and 100.00€ with 7%
        * 10% should be calculated 
        * 200€ (price amount) * 10% => 20.00%
        * 50% of the price is based on 19% tax calculation
        * 50% of the price is based on  7% tax calculation
        
And the following tax calculation services:
* `\Shopware\Cart\Tax\TaxRuleCalculator`
    * Tax calculation is based on a price with a simple tax rate
    * Example
        * 100.00 € should be calculated with a 19% tax rate
    
* `\Shopware\Cart\Tax\PercentageTaxRuleCalculator`
    * Tax calculation is based on a percentage price value
    * Example: 
        * total price: 100.00 €
        * 90.00€ should be calculated with a 19% tax rate
        * 10.00€ should be calculated with a 7% tax rate

## Extensibility concept
All services in the CartBundle defined inside the service container, which means each service can be replaced or decorated. 

### Example - Discount for new customers
The following examples shows one possible solution for creating dynamic discounts for new customers.  
```
namespace SwagCartExtension\Cart;

use Doctrine\DBAL\Connection;
use Shopware\Cart\Cart\CartContainer;
use Shopware\Cart\Cart\CartProcessorInterface;
use Shopware\Cart\Cart\ProcessorCart;
use Shopware\Cart\LineItem\Discount;
use Shopware\Cart\Price\PercentagePriceCalculator;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

class NewCustomerDiscountProcessor implements CartProcessorInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var PercentagePriceCalculator
     */
    private $percentagePriceCalculator;

    /**
     * @param Connection $connection
     * @param PercentagePriceCalculator $percentagePriceCalculator
     */
    public function __construct(
        Connection $connection,
        PercentagePriceCalculator $percentagePriceCalculator
    ) {
        $this->connection = $connection;
        $this->percentagePriceCalculator = $percentagePriceCalculator;
    }


    public function process(
        CartContainer $cartContainer,
        ProcessorCart $processorCart,
        StructCollection $dataCollection,
        ShopContext $context
    ): void
    {
        //no logged in state?
        if (!$context->getCustomer()) {
            return;
        }

        //validate if customer should get discount
        if (!$this->isNewCustomer($context->getCustomer()->getUuid())) {
            return;
        }

        //get access to all goods inside the cart
        $goods = $processorCart->getCalculatedLineItems()->filterGoods();

        //use core calculator for percentage price calculation with all goods prices
        $discount = $this->percentagePriceCalculator->calculate(
            -10,
            $goods->getPrices(),
            $context
        );

        //add calculated discount to cart
        $processorCart->getCalculatedLineItems()->add(
            new Discount('new-customer-discount', $discount, 'New customer discount')
        );
    }

    /**
     * Validates if the provided customer id is a new customer in the system
     * and should get the `new customer discount`
     *
     * @param string $customerUuid
     * @return bool
     */
    private function isNewCustomer($customerUuid)
    {
        $hasOrder = $this->connection->createQueryBuilder()
            ->select('1')
            ->from('order', 'orders')
            ->where('orders.customer_uuid = :id')
            ->setMaxResults(1)
            ->setParameter(':id', $customerUuid)
            ->execute()
            ->fetch(\PDO::FETCH_COLUMN);

        return !$hasOrder;
    }
}
```
The first two conditions validate if a customer is logged in and if the customer has already made an order.
After the validation passes that the customer is a new customer, the processor first collects all calculated goods in the cart `$goods = $processorCart->getCalculatedLineItems()->filterGoods();`.
To calculate the percentage discount for the `new customer discount` the processor uses the Shopware core calculator `\Shopware\Cart\Price\PercentagePriceCalculator`.

The processor has to be registered over the `cart_processor` container tag.
 The priority defines at which position the calculator has to be executed (after product calculation, before voucher, ...).
```
<service id="swag_cart_extension.new_customer_discount_processor" class="SwagCartExtension\Cart\NewCustomerDiscountProcessor">
    <argument type="service" id="dbal_connection" />
    <argument type="service" id="cart.price.percentage_calculator" />
    <tag name="cart.processor" priority="900" />
</service>
```

### Example - Blacklisted products
The following examples shows a possible solution for preventing some products from entering the cart:

```
namespace SwagBlacklist\Cart;


use Shopware\Cart\Cart\CartContainer;
use Shopware\Cart\Cart\CartProcessorInterface;
use Shopware\Cart\Cart\ProcessorCart;
use Shopware\Cart\LineItem\LineItem;
use Shopware\Cart\LineItem\LineItemCollection;
use Shopware\Cart\Product\ProductProcessor;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

class BlacklistedProductProcessor implements CartProcessorInterface
{
    private $blackList = [
        'SW10239'
    ];

    public function process(
        CartContainer $cartContainer,
        ProcessorCart $processorCart,
        StructCollection $dataCollection,
        ShopContext $context
    ): void
    {
        /** @var LineItemCollection $products */
        $products = $cartContainer->getLineItems()->filterType(ProductProcessor::TYPE_PRODUCT);

        /** @var LineItem $product */
        foreach ($products as $product) {
            if (in_array($product->getIdentifier(), $this->blackList)) {
                $cartContainer->getLineItems()->remove($product->getIdentifier());
            }
        }
    }
}
```

The service is registered as follow:
```
<service id="swag_blacklist_.blacklisted_product_processor" class="SwagBlacklist\Cart\BlackListedProductProcessor">
    <tag name="cart.processor" priority="1001" />
</service>
```
Using a high priority defines an early position inside the cart calculation for this processor.
The `\Shopware\Cart\Product\ProductProcessor` is registered with priority 1000, which means it is executed after this blacklist processor.
