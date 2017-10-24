# Processor pattern of cart 

## Introduction
The processor pattern is the access point which allows to define at which point of calculation a specified tasks has to be executed.
For example the cart doesn't know at which time a product or voucher has to be calculated, this is task of the ProductProcessor and the VoucherProcessor.

## Register processor
Cart processors are registered over an symfony di container tag named `cart.processor`.
To define the correct calculation time, the tag supports the `priority` attribute:
```xml
<service id="cart.product.processor" class="Shopware\Cart\Product\ProductProcessor">
    <tag name="cart.processor" priority="1000" />
</service>
```
A high `priority` defines to get early access in the cart process. 
Currently the following processors are registered.

| priority | service id | class | task |
| -------- | ---------- | ----- | ---- |
| 1000 | cart.product.processor | Shopware\Cart\Product\ProductProcessor  |  handle products which added to the cart by customer  |
| 800 | cart.voucher.processor | Shopware\Cart\Voucher\VoucherProcessor | handle vouchers which added to the cart by customer |
| 600 | dynamic |  |  |
| 400 | cart.delivery.separator_processor | Shopware\Cart\Delivery\DeliverySeparatorProcessor  | separates the different deliverable line items to deliveries |
| 200 | cart.delivery.calculator_processor | Shopware\Cart\Delivery\DeliveryCalculatorProcessor | calculates all delivers which created |

## How a processor (should) work
A processor, in shopware, only filtering line items or deliveries of a cart. 
Calculations or other business logic like splitting deliveries or calculating them should defined in a separated service.
A simple processor could looks like as follow:
```php
<?php

class ProductProcessor implements CartProcessorInterface
{
    const TYPE_PRODUCT = 'product';

    /**
     * @var ProductCalculator
     */
    private $calculator;

    public function process(
        CartContainer $cartContainer,
        ProcessorCart $processorCart,
        StructCollection $dataCollection,
        ShopContext $context
    ): void {

        $collection = $cartContainer->getLineItems()->filterType(self::TYPE_PRODUCT);
        if ($collection->count() === 0) {
            return;
        }

        $products = $this->calculator->calculate($collection, $context, $dataCollection);
        $processorCart->getCalculatedLineItems()->fill($products->getElements());
    }
}
```



