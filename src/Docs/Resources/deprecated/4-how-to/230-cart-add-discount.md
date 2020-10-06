[titleEn]: <>(Add discount for specific products)
[metaDescriptionEn]: <>(In this HowTo you will learn to add a discount for specific products.)
[hash]: <>(article:how_to_cart_add_discount)

## Overview

In this HowTo you will learn to add a discount for specific products.
The same way can be used to add surcharges to the cart instead.

## Scenario

In this example you want to add a discount of ten percent for all products, whose name contains "Awesome".
E.g. there's a product called "Awesome Zebra", as well as a product "Glorious Horse", you only want the
discount to apply for the first product.

You also got a plugin running already.
If you don't know how to do this, have a look our [plugin quick start guide](./../2-internals/4-plugins/010-plugin-quick-start.md).

## Adding a discount

To accomplish the goal of adding a discount to the cart, you should use the processor pattern.
For this you need to create your own cart processor.

All adjustments are done in the `process` method, where the product items already own a name and a price.

Let's start with the actual example code:
```php
<?php declare(strict_types=1);

namespace Swag\CartAddDiscountForProduct\Core\Checkout;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AwesomeProductsCollector implements CartProcessorInterface
{
    /**
     * @var PercentagePriceCalculator
     */
    private $calculator;

    public function __construct(PercentagePriceCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $products = $this->findAwesomeProducts($toCalculate);

        // no awesome products found? early return
        if ($products->count() === 0) {
            return;
        }

        $discountLineItem = $this->createDiscount('AWESOME_DISCOUNT');

        // declare price definition to define how this price is calculated
        $definition = PercentagePriceDefinition::create(-10, new LineItemRule(LineItemRule::OPERATOR_EQ, $products->getKeys()));

        $discountLineItem->setPriceDefinition($definition);

        // calculate price
        $discountLineItem->setPrice(
            $this->calculator->calculate($definition->getPercentage(), $products->getPrices(), $context)
        );

        // add discount to new cart
        $toCalculate->add($discountLineItem);
    }

    private function findAwesomeProducts(Cart $cart): LineItemCollection
    {
        return $cart->getLineItems()->filter(function (LineItem $item) {
            if ($item->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                return false;
            }

            $awesomeInLabel = stripos($item->getLabel(), 'awesome') !== false;

            if (!$awesomeInLabel) {
                return false;
            }

            return $item;
        });
    }

    private function createDiscount(string $name): LineItem
    {
        $discountLineItem = new LineItem($name, 'awesome_discount', null, 1);

        $discountLineItem->setLabel('\'You are awesome!\' discount');
        $discountLineItem->setGood(false);
        $discountLineItem->setStackable(false);
        $discountLineItem->setRemovable(false);

        return $discountLineItem;
    }
}
```

What's done here is rather simple.
First of all, all the products containing the string 'awesome' in their name are fetched.
Also, a few information are saved into variables, since we'll need them several times.

If no product in the cart matches your condition, you can early return in the `process` method.
Afterwards you create a new line item for the new discount. 
For the latter, you want the line item to not be stackable and it shouldn't be removable either.

So let's get to the important part, which is the price.
For a percentage discount, you have to use the `PercentagePriceDefinition`.
It consists of an actual value, the currency precision and, if necessary, some rules to apply to.
This definition is required for the cart to tell the core how this price can be recalculated even if the plugin would be uninstalled.

Shopware 6 comes with a so called `LineItemRule`, which requires two parameters:
- The operator being used, currently only `LineItemRule::OPERATOR_EQ` (Equals) and `LineItemRule::OPERATOR_NEQ` (Not equals) are supported
- The identifiers to apply the rule to. Pass the line item identifiers here, in this case the identifiers of the previously filtered products

After adding the definition to the line item, you have to calculate the current price of the discount. Therefore you can use the `PercentagePriceCalculator` of the core.
The last step is to add the discount to the new cart which is provided as `Cart $toCalculate`.

That's it for the main code from your custom `CartProcessor`.

### Registering the collector

Here's a quick example from the `services.xml`, which defines your custom `CartProcessor` using the DI service tag `shopware.cart.processor`.
The priority is used to get access to the calculation after the product processor handled the products. 

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Swag\CartAddDiscountForProduct\Core\Checkout\AwesomeProductsCollector">
            <argument type="service" id="Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator"/>

            <!-- after product cart processor -->
            <tag name="shopware.cart.processor" priority="4500"/>
        </service>
    </services>
</container>
```

## Source

There's a GitHub repository available, containing a full example source.
Check it out [here](https://github.com/shopware/swag-docs-cart-add-discount).
