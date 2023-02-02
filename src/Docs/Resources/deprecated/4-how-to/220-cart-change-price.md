[titleEn]: <>(Change price of item in cart)
[metaDescriptionEn]: <>(This HowTo will tackle the issue of changing the price of an item itself in the cart dynamically.)
[hash]: <>(article:how_to_cart_change_price)

## Overview

This HowTo will tackle the issue of changing the price of an item itself in the cart dynamically.
The following example is **not** recommended if you want to add a discount / surcharge to your products.
Make sure to check out the HowTo about [adding a discount into the cart dynamically](./230-cart-add-discount.md).

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

To accomplish the goal of changing an item in the cart, you should use the collector pattern.
For this you need to create your own cart collector.

The collector basically compares the product IDs of the products in the cart with the product IDs from the custom table.
If there's any match, the price has to be overwritten.

Here's an working example about how this could work:
```php
<?php declare(strict_types=1);

namespace Swag\CartChangePrice\Cart\Checkout;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Swag\CartChangePrice\Cart\Checkout\OverwrittenPrice\OverwrittenPriceEntity;

class OverwrittenPriceCollector implements CartDataCollectorInterface, CartProcessorInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $overwritePriceRepository;

    /**
     * @var QuantityPriceCalculator
     */
    private $calculator;

    public function __construct(
        EntityRepositoryInterface $overwritePriceRepository,
        QuantityPriceCalculator $calculator
    ) {
        $this->overwritePriceRepository = $overwritePriceRepository;
        $this->calculator = $calculator;
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        // get all product ids of current cart
        $productIds = $original->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE)->getReferenceIds();

        // remove all product ids which are already fetched from the database
        $filtered = $this->filterAlreadyFetchedPrices($productIds, $data);

        if (empty($filtered)) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('productId', $filtered));

        // fetch prices from database
        $prices = $this->overwritePriceRepository->search($criteria, $context->getContext());;

        foreach ($filtered as $id) {
            $key = $this->buildKey($id);

            $price = null;
            // find price for the current product id
            foreach ($prices as $current) {
                if ($current->getProductId() === $id) {
                    $price = $current;
                    break;
                }
            }

            // we have to set a value for each product id to prevent duplicate queries in next calculation
            $data->set($key, $price);
        }
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        // get all product line items
        $products = $toCalculate->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        foreach ($products as $product) {
            $key = $this->buildKey($product->getReferencedId());

            // no overwritten price? continue with next product
            if (!$data->has($key) || $data->get($key) === null) {
                continue;
            }

            /** @var OverwrittenPriceEntity $price */
            $price = $data->get($key);

            // build new price definition
            $definition = QuantityPriceDefinition::create(
                $price->getPrice(),
                $product->getPrice()->getTaxRules(),
                $product->getPrice()->getQuantity()
            );

            // build CalculatedPrice over calculator class for overwitten price
            $calculated = $this->calculator->calculate($definition, $context);

            // set new price into line item
            $product->setPrice($calculated);
            $product->setPriceDefinition($definition);
        }
    }

    private function filterAlreadyFetchedPrices(array $productIds, CartDataCollection $data): array
    {
        $filtered = [];

        foreach ($productIds as $id) {
            $key = $this->buildKey($id);

            // already fetched from database?
            if ($data->has($key)) {
                continue;
            }

            $filtered[] = $id;
        }

        return $filtered;
    }

    private function buildKey(string $id): string
    {
        return 'price-overwrite-'.$id;
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
            <argument type="service" id="Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator"/>

            <!-- after product collector/processor -->
            <tag name="shopware.cart.processor" priority="4500" />
            <tag name="shopware.cart.collector" priority="4500" />
        </service>
    </services>
</container>
```

## Source

There's a GitHub repository available, containing a full example source.
Check it out [here](https://github.com/shopware/swag-docs-cart-change-price).
