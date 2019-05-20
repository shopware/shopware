[titleEn]: <>(Add discount for specific products)
[metaDescriptionEn]: <>(In this HowTo you will learn to add a discount for specific products.)

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

To accomplish the goal of adding a discount to the cart, you should use the enrichment pattern.
For this you need to create your own cart collector.

You can leave both methods `prepare`, as well as `collect`, since you neither need to prepare the products, nor
do you have to collect any data from the database.
All adjustments are done in the `enrich` method, where the product items already own a name.

Let's start with the actual example code:
```php
<?php declare(strict_types=1);

namespace Swag\CartAddDiscountForProduct\Core\Checkout;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CollectorInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemRule;
use Shopware\Core\Framework\Struct\StructCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AwesomeProductsCollector implements CollectorInterface
{
    public function prepare(StructCollection $definitions, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
    }

    public function collect(StructCollection $fetchDefinitions, StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {

    }

    public function enrich(StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        // Figure out all products containing 'awesome' in its name
        $products = $this->findAwesomeProducts($cart);

        $name = 'AWESOME_DISCOUNT';
        $discountAlreadyInCart = $cart->has($name);

        // No products matched, remove all discounts if any in the cart
        if ($products->count() === 0) {
            if ($discountAlreadyInCart) {
                $cart->getLineItems()->remove($name);
            }

            return;
        }

        // If the discount is already in the cart, fetch it from the cart. Otherwise, create it
        if (!$discountAlreadyInCart) {
            $discountLineItem = $this->createNewDiscountLineItem($name);
        } else {
            $discountLineItem = $cart->get($name);
        }

        // Set a new percentage price definition
        $discountLineItem->setPriceDefinition(
            new PercentagePriceDefinition(
                -10,
                $context->getContext()->getCurrencyPrecision(),
                new LineItemRule(LineItemRule::OPERATOR_EQ, $products->getKeys())
            )
        );

        // If the discount line item was in cart already, do not add it again
        if (!$discountAlreadyInCart) {
            $cart->add($discountLineItem);
        }
    }

    private function findAwesomeProducts(Cart $cart): \Shopware\Core\Checkout\Cart\LineItem\LineItemCollection
    {
        return $cart->getLineItems()->filter(function (LineItem $item) {
            // The discount itself has the name 'awesome' - so check if the type matches to our discount
            if ($item->getType() === 'awesome_discount') {
                return false;
            }

            $awesomeInLabel = stripos($item->getLabel(), 'awesome') !== false;

            if (!$awesomeInLabel) {
                return false;
            }

            return $item;
        });
    }

    private function createNewDiscountLineItem(string $name): LineItem
    {
        $discountLineItem = new LineItem($name, 'awesome_discount');

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
This is not done in the `prepare` method, since the name is not available there yet.
Also, a few information are saved into variables, since we'll need them several times.

If no product in the cart matches your condition, you remove any discounts matching your selected discount name.
E.g. if you remove the last `awesome` product from your cart, you also want the discount to be removed as well.

Afterwards you need access to the discount line item, which can either be part of the cart already, since an awesome product
was previously put into the cart, or you gotta create a new line item yourself.
For the latter, you want the line item to not be stackable and it shouldn't be removable either.

So let's get to the important part, which is the price.
For a percentage discount, you have to use the `PercentagePriceDefinition`.
It consists of an actual value, the currency precision and, if necessary, some rules to apply to.

The Shopware platform comes with a so called `LineItemRule`, which requires two parameters:
- The operator being used, currently only `LineItemRule::OPERATOR_EQ` (Equals) and `LineItemRule::OPERATOR_NEQ` (Not equals) are supported
- The identifiers to apply the rule to. Pass the line item identifiers here, in this case the identifiers of the previously filtered products

The last step is to check once more if the cart already knew the discount line item.
If not, you need to add it here, otherwise do nothing.

That's it for the main code from your custom `CartCollector`.

### Registering the collector

Here's a quick example from the `services.xml`, which defines your custom `CartCollector` using the DI service tag `shopware.cart.collector`.

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Swag\CartAddDiscountForProduct\Core\Checkout\AwesomeProductsCollector">
            <tag name="shopware.cart.collector" priority="-100"/>
        </service>
    </services>
</container>
```

## Source

There's a GitHub repository available, containing a full example source.
Check it out [here](https://github.com/shopware/swag-docs-cart-add-discount).
