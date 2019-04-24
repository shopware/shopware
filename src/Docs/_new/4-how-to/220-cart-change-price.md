[titleEn]: <>(Change price of item in cart)
[metaDescriptionEn]: <>(This HowTo will tackle the issue of changing the price of an item itself in the cart dynamically.)

## Overview

This HowTo will tackle the issue of changing the price of an item itself in the cart dynamically.
The following example is **not** recommended if you want to add a discount / surcharge to your products.
Make sure to check out the HowTo about [adding a discount into the cart dynamically](010-fix-me.md).

Changing the price like it's done in the following example should rarely be done and only with great caution.
A live-shopping plugin would be a good example about when to actually change an item's price instead of adding
a discount / surcharge.


## Scenario

For this HowTo the following scenario is given:

You want to create a plugin, whose main purpose it is to overwrite the price of an item in the cart dynamically.
In this example, the prices are fetched from a database-table.
You've already created your own working plugin with a custom entity for those prices.
If you don't know how that's done, have a look at our [Plugin quick start guide](./../2-internals/4-plugins/010-plugin-quick-start.md) and
our [HowTo to create a custom entity](./050-custom-entity.md).

The custom entity could look something like that:

```php
<?php declare(strict_types=1);

namespace Swag\CartChangePrice\Cart\Checkout\OverwrittenPrice;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OverwrittenPriceEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var ProductEntity
     */
    protected $product;

    /**
     * @var string
     */
    protected $productId;

    /**
     * @var float
     */
    protected $price;

    public function getProduct(): ProductEntity
    {
        return $this->product;
    }

    public function setProduct(ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }
}
```

As you see, it comes with a mapping to a product and a custom price field.

This example does **not** take care of displaying that price on the detail page.
It only shows how to overwrite the price of an item in the cart due to some conditions.

## Changing the price

To accomplish the goal of changing an item in the cart, you should use the [enrichment pattern](010-fix-me.md).
For this you need to create your own cart collector, as explained in the documentation linked above.

The collector basically compares the product IDs of the products in the cart with the product IDs from the custom table.
If there's any match, the price has to be overwritten.

Here's an working example about how this could work:
```php
<?php declare(strict_types=1);

namespace Swag\CartChangePrice\Cart\Checkout;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Struct\StructCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Swag\CartChangePrice\Cart\Checkout\OverwrittenPrice\OverwrittenPriceEntity;

class OverwrittenPriceCollector implements \Shopware\Core\Checkout\Cart\CollectorInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $overwritePriceRepository;

    public function __construct(EntityRepositoryInterface $overwritePriceRepository)
    {
        $this->overwritePriceRepository = $overwritePriceRepository;
    }

    public function prepare(StructCollection $definitions, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        // Simply consider all items in the cart and pass it to the collection
        // We do not want to filter for the products from our custom database table here, since database actions are supposed to be done
        // in the `collect` method
        $definitions->add(new OverwrittenPriceFetchDefinition($cart->getLineItems()->filterGoods()->getKeys()));
    }

    public function collect(StructCollection $fetchDefinitions, StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        // Fetch the items from the collection again.
        $priceOverwriteFetchDefinitions = $fetchDefinitions->filterInstance(OverwrittenPriceFetchDefinition::class);
        if ($priceOverwriteFetchDefinitions->count() === 0) {
            return;
        }

        $productIds = [[]];
        /** @var OverwrittenPriceFetchDefinition $fetchDefinition */
        foreach ($priceOverwriteFetchDefinitions as $fetchDefinition) {
            // Collect all product IDs from the items in the cart
            $productIds[] = $fetchDefinition->getProductIds();
        }

        // Flatten the array
        $productIds = array_unique(array_merge(...$productIds));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('productId', $productIds));
        // Check if any product id, which was collected earlier, matches with the custom table product IDs
        $overwrittenPrices = $this->overwritePriceRepository->search($criteria, $context->getContext());

        // Necessary for the `enrich` method. Contains all product IDs, whose prices need to be overwritten
        $data->set('overwritten_price', $overwrittenPrices);
    }

    public function enrich(StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        // If no price has to be overwritten, do nothing
        if (!$data->has('overwritten_price')) {
            return;
        }

        $overwrittenPrices = $data->get('overwritten_price');

        // If no price has to be overwritten, do nothing
        if (count($overwrittenPrices) === 0) {
            return;
        }

        /** @var OverwrittenPriceEntity $overwrittenPrice */
        foreach ($overwrittenPrices as $overwrittenPrice) {
            // Fetch the cart item matching to the product ID
            $matchedCartItem = $cart->getLineItems()->get($overwrittenPrice->getProductId());

            if (!$matchedCartItem) {
                continue;
            }

            /** @var QuantityPriceDefinition $oldPriceDefinition */
            $oldPriceDefinition = $matchedCartItem->getPriceDefinition();
            // Overwrite price definition with the new price, hence the `$overwrittenPrice->getPrice()` call
            $matchedCartItem->setPriceDefinition(
                new QuantityPriceDefinition(
                    $overwrittenPrice->getPrice(),
                    $oldPriceDefinition->getTaxRules(),
                    $oldPriceDefinition->getPrecision(),
                    $oldPriceDefinition->getQuantity(),
                    $oldPriceDefinition->isCalculated()
                )
            );
        }
    }
}
```

But why is there a custom fetch definition `OverwrittenPriceFetchDefinition`?
Imagine you wouldn't select all items from the cart in the `prepare` method, but only line items, which match the condition X.
In the `collect` method, you're only considering the products from a `OverwrittenPriceFetchDefinition`.

Now, a third party developer wants to add condition Y to your use-case, so his products are also considered by your code.
He can now simply achieve this, by adding his own collector, which returns his products in a new instance of your `OverwrittenPriceFetchDefinition` - and that's it already,
his plugin would already be compatible with your custom collector.
If you were to use a default FetchDefinition, chances are high, that many other collectors would also deal with his products in some way he did not anticipate.

So here's an example of the said `OverwrittenPriceFetchDefinition`:
```php
<?php declare(strict_types=1);

namespace Swag\CartChangePrice\Cart\Checkout;

use Shopware\Core\Framework\Struct\Struct;

class OverwrittenPriceFetchDefinition extends Struct
{
    /**
     * @var string[]
     */
    protected $productIds;

    /**
     * @param string[] $productIds
     */
    public function __construct(array $productIds)
    {
        $this->productIds = $productIds;
    }

    /**
     * @return string[]
     */
    public function getProductIds(): array
    {
        return $this->productIds;
    }
}
```

And for completion's sake, the respective `services.xml`, which registers the collector in the first instance.
```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <!-- <service id="YourCustomEntityDefinition" /> -->
    
        <service id="Swag\CartChangePrice\Cart\Checkout\OverwrittenPriceCollector">
            <argument type="service" id="overwritten_price.repository" />
            <tag name="Shopware\Core\Checkout\Cart\CollectorInterface" />
        </service>
    </services>
</container>
```

## Source

There's a GitHub repository available, containing a full example source.
Check it out [here](https://github.com/shopware/swag-docs-cart-change-price).
