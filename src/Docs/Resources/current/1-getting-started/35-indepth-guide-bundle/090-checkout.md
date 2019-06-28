[titleEn]: <>(Step 9: Checkout logic)

## Creating a cart processor/collector

To implement an extension for the cart you have to implement two interfaces:
1. `\Shopware\Core\Checkout\Cart\CartDataCollectorInterface`
2. `\Shopware\Core\Checkout\Cart\CartProcessorInterface`

What are these classes for?

With the `\Shopware\Core\Checkout\Cart\CartDataCollectorInterface` you can enrich your cart with further data. So far you have only one generic line item of type `swagbundle` into the cart, but does not define what price it has or what products are in it.
Implementing the `\Shopware\Core\Checkout\Cart\CartProcessorInterface`, you can intervene in the calculation process of the cart. Here you have for example the possibility to access subtotals.

Why are these two processes separated from each other?

As you will notice later, the separation of these two processes offers a serious advantage. But first of all the information is sufficient that you can control more precisely when you want to enrich data and when you want to calculate prices.

### Implementing the `collect` function

As described above, your task here is to enrich the bundle line items in the cart with information. 
Below is the complete source code for implementing your `\Shopware\Core\Checkout\Cart\CartDataCollectorInterface` for your bundle plugin.
This is explained in more detail below.

```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Checkout\Bundle\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\QuantityInformation;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Swag\BundleExample\Core\Content\Bundle\BundleCollection;
use Swag\BundleExample\Core\Content\Bundle\BundleEntity;

class BundleCartProcessor implements CartDataCollectorInterface
{
    private const TYPE = 'swagbundle';
    private const DISCOUNT_TYPE = 'swagbundle-discount';
    private const DATA_KEY = 'swag_bundle-';
    private const DISCOUNT_TYPE_ABSOLUTE = 'absolute';
    private const DISCOUNT_TYPE_PERCENTAGE = 'percentage';

    /**
     * @var EntityRepositoryInterface
     */
    private $bundleRepository;

    public function __construct(EntityRepositoryInterface $bundleRepository)
    {
        $this->bundleRepository = $bundleRepository;
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $bundleLineItems = $original->getLineItems()
            ->filterType(self::TYPE);

        // no bundles in cart? exit
        if (\count($bundleLineItems) === 0) {
            return;
        }

        // fetch missing bundle information from database
        $bundles = $this->fetchBundles($bundleLineItems, $data, $context);

        /** @var BundleEntity $bundle */
        foreach ($bundles as $bundle) {
            // ensure all line items have a data entry
            $data->set(self::DATA_KEY . $bundle->getId(), $bundle);
        }

        foreach ($bundleLineItems as $bundleLineItem) {
            $bundle = $data->get(self::DATA_KEY . $bundleLineItem->getReferencedId());

            // enrich bundle information with quantity and delivery information
            $this->enrichBundle($bundleLineItem, $bundle);

            // add bundle products which are not already assigned
            $this->addMissingProducts($bundleLineItem, $bundle);

            // add bundle discount if not already assigned
            $this->addDiscount($bundleLineItem, $bundle, $context);
        }
    }

    /**
     * Fetches all Bundles that are not already stored in data
     */
    private function fetchBundles(LineItemCollection $bundleLineItems, CartDataCollection $data, SalesChannelContext $context): BundleCollection
    {
        $bundleIds = $bundleLineItems->getReferenceIds();

        $filtered = [];
        foreach ($bundleIds as $bundleId) {
            // If data already contains the bundle we don't need to fetch it again
            if ($data->has(self::DATA_KEY . $bundleId)) {
                continue;
            }

            $filtered[] = $bundleId;
        }

        $criteria = new Criteria($filtered);
        $criteria->addAssociation('products');
        /** @var BundleCollection $bundles */
        $bundles = $this->bundleRepository->search($criteria, $context->getContext())->getEntities();

        return $bundles;
    }

    private function enrichBundle(LineItem $bundleLineItem, BundleEntity $bundle): void
    {
        if (!$bundleLineItem->getLabel()) {
            $bundleLineItem->setLabel($bundle->getName());
        }

        $bundleLineItem->setRemovable(true)
            ->setStackable(true)
            ->setDeliveryInformation(
                new DeliveryInformation(
                    (int)$bundle->getProducts()->first()->getStock(),
                    (float)$bundle->getProducts()->first()->getWeight(),
                    $bundle->getProducts()->first()->getDeliveryDate(),
                    $bundle->getProducts()->first()->getRestockDeliveryDate(),
                    $bundle->getProducts()->first()->getShippingFree()
                )
            )
            ->setQuantityInformation(new QuantityInformation());
    }

    private function addMissingProducts(LineItem $bundleLineItem, BundleEntity $bundle): void
    {
        foreach ($bundle->getProducts()->getIds() as $productId) {
            // if the bundleLineItem already contains the product we can skip it
            if ($bundleLineItem->getChildren()->has($productId)) {
                continue;
            }

            // the ProductCartProcessor will enrich the product further
            $productLineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId);

            $bundleLineItem->addChild($productLineItem);
        }
    }

    private function addDiscount(LineItem $bundleLineItem, BundleEntity $bundle, SalesChannelContext $context): void
    {
        if ($this->getDiscount($bundleLineItem)) {
            return;
        }

        $discount = $this->createDiscount($bundle, $context);

        if ($discount) {
            $bundleLineItem->addChild($discount);
        }
    }

    private function getDiscount(LineItem $bundle): ?LineItem
    {
        return $bundle->getChildren()->get($bundle->getReferencedId() . '-discount');
    }

    private function createDiscount(BundleEntity $bundleData, SalesChannelContext $context): ?LineItem
    {
        if ($bundleData->getDiscount() === 0) {
            return null;
        }

        switch ($bundleData->getDiscountType()) {
            case self::DISCOUNT_TYPE_ABSOLUTE:
                $priceDefinition = new AbsolutePriceDefinition($bundleData->getDiscount() * -1, $context->getContext()->getCurrencyPrecision());
                $label = 'Absolute bundle voucher';
                break;

            case self::DISCOUNT_TYPE_PERCENTAGE:
                $priceDefinition = new PercentagePriceDefinition($bundleData->getDiscount() * -1, $context->getContext()->getCurrencyPrecision());
                $label = sprintf('Percental bundle voucher (%s%%)', $bundleData->getDiscount());
                break;

            default:
                throw new \RuntimeException('Invalid discount type.');
        }

        $discount = new LineItem(
            $bundleData->getId() . '-discount',
            self::DISCOUNT_TYPE,
            $bundleData->getId()
        );

        $discount->setPriceDefinition($priceDefinition)
            ->setLabel($label)
            ->setGood(false);

        return $discount;
    }
}
```

First you should check if there is a bundle in your cart. For this you can use the `filterType` method of the `LineItemCollection`.
```php
// collect all bundle in cart
$bundleLineItems = $original->getLineItems()->filterType('swagbundle');
``` 

If there are no bundles in your cart, you can already exit here:
```php
// no bundles in cart? exit
if (\count($bundleLineItems) === 0) {
    return;
}
```

For the cart bundles you have to fetch the corresponding information from the database and add the information into the `\Shopware\Core\Checkout\Cart\LineItem\CartDataCollection`. Each bundle gets its own entry here:

```php
// fetch missing bundle information from database
$bundles = $this->fetchBundles($bundleLineItems, $data, $context);

/** @var BundleEntity $bundle */
foreach ($bundles as $bundle) {
    // ensure all line items have a data entry
    $data->set(self::DATA_KEY . $bundle->getId(), $bundle);
}
```

<p class="alert is--error">
IMPORTANT: Please note that the `collect` method is called several times. 
So that you do not fetch the data unnecessarily often from the database, you must check whether the data is not already in the `\Shopware\Core\Checkout\Cart\LineItem\CartDataCollection`.
</p>


```php
/**
 * Fetches all Bundles that are not already stored in data
 */
private function fetchBundles(LineItemCollection $bundleLineItems, CartDataCollection $data, SalesChannelContext $context): BundleCollection
{
    $bundleIds = $bundleLineItems->getReferenceIds();

    $filtered = [];
    foreach ($bundleIds as $bundleId) {
        // If data already contains the bundle we don't need to fetch it again
        if ($data->has(self::DATA_KEY . $bundleId)) {
            continue;
        }

        $filtered[] = $bundleId;
    }

    // ...

    return $bundles;
}
```

Once you have filtered the missing bundles, you can use the bundle repository to select the information from the database. 
Since the bundle line items may not yet contain the products, they must also be fetched from the database:
```php
/**
 * Fetches all Bundles that are not already stored in data
 */
private function fetchBundles(LineItemCollection $bundleLineItems, CartDataCollection $data, SalesChannelContext $context): BundleCollection
{
    // ...

    $criteria = new Criteria($filtered);
    $criteria->addAssociation('products');

    /** @var BundleCollection $bundles */
    $bundles = $this->bundleRepository->search($criteria, $context->getContext())->getEntities();

    return $bundles;
}
```

Now that you have the database information for all bundles, you can enrich the line items with data:

```php
public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
{
    // ...

    foreach ($bundleLineItems as $bundleLineItem) {
        $bundle = $data->get(self::DATA_KEY . $bundleLineItem->getReferencedId());

        // enrich bundle information with quantity and delivery information
        $this->enrichBundle($bundleLineItem, $bundle);

        // add bundle products which are not already assigned
        $this->addMissingProducts($bundleLineItem, $bundle);

        // add bundle discount if not already assigned
        $this->addDiscount($bundleLineItem, $bundle, $context);
    }
}
```

In this loop you enrich the bundle with three different information:
* `enrichBundle` Here you enrich the bundle line item itself with information (label, description, etc.)
* `addMissingProducts` Here you add missing products to the bundle
* `addDiscount` Lastly the discount is added to the bundle

Let's first have a look at the `enrichBundle` function:

```php
private function enrichBundle(LineItem $bundleLineItem, BundleEntity $bundle): void
{
    if (!$bundleLineItem->getLabel()) {
        $bundleLineItem->setLabel($bundle->getName());
    }

    $bundleLineItem->setRemovable(true)
        ->setStackable(true)
        ->setDeliveryInformation(
            new DeliveryInformation(
                (int)$bundle->getProducts()->first()->getStock(),
                (float)$bundle->getProducts()->first()->getWeight(),
                $bundle->getProducts()->first()->getDeliveryDate(),
                $bundle->getProducts()->first()->getRestockDeliveryDate(),
                $bundle->getProducts()->first()->getShippingFree()
            )
        )
        ->setQuantityInformation(new QuantityInformation());
}
```

The following information is now added to the bundle itself:
* `$bundleLineItem->setLabel(...)` Here the label for the bundle is set
* `$bundleLineItem->setRemovable(true)` About this you define that the customer can remove the bundle from the cart by himself
* `$bundleLineItem->setStackable(true)` By marking it as `stackable` you define that the customer may change the quantity in the cart.
* `$bundleLineItem->setDeliveryInformation(...)` Here you set the information for distribution into a delivery. For the sake of simplicity, the information of the first product is simply used here.
* `$bundleLineIten->setQuantityInformation()` You can use the `Shopware\Core\Checkout\Cart\LineItem\QuantityInformation` to define minimum and maximum orders.

Now that you have added all the information to the bundle line item, the products must be placed in the bundle using the `addMissingProducts` function:
```php
private function addMissingProducts(LineItem $bundleLineItem, BundleEntity $bundle): void
{
    foreach ($bundle->getProducts()->getIds() as $productId) {
        // if the bundleLineItem already contains the product we can skip it
        if ($bundleLineItem->getChildren()->has($productId)) {
            continue;
        }

        // the ProductCartProcessor will enrich the product further
        $productLineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId);

        $bundleLineItem->addChild($productLineItem);
    }
}
```

For this you simply iterate over all products of the `BundleEntity $bundle` and check if it is not yet in.
Here is an interesting place. The Shopware cart already provides that there may be nested items in the cart. Therefore each line item has the function `getChildren` which returns a `LineItemCollection`.

```php
foreach ($bundle->getProducts()->getIds() as $productId) {
    // if the bundleLineItem already contains the product we can skip it
    if ($bundleLineItem->getChildren()->has($productId)) {
        continue;
    }

    // ...
}
``` 

If the product was not added as a child, you can simply add a new `LineItem` with the type `\Shopware\Core\Checkout\Cart\LineItem\LineItem::PRODUCT_LINE_ITEM_TYPE`.

```php
private function addMissingProducts(LineItem $bundleLineItem, BundleEntity $bundle): void
{
    foreach ($bundle->getProducts()->getIds() as $productId) {
        // ...

        // the ProductCartProcessor will enrich the product further
        $productLineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId);

        $bundleLineItem->addChild($productLineItem);
    }
}
```

This is now the place where the differentiation between `processor` and `collector` comes to bear. 

If you register your `\Shopware\Core\Checkout\Cart\CartDataCollectorInterface` before the `\Shopware\Core\Content\Product\Product\Cart\ProductCartProcessor`, it will take care
that the `LineItem` of type `\Shopware\Core\Checkout\Cart\LineItem\LineItem::PRODUCT_LINE_ITEM_TYPE`, added by you, is enriched with the required information.

So you don't have to take care of all the information like: price, label, cover, delivery information, quantity information.
The `\Shopware\Core\Content\Product\Product\Cart\ProductCartProcessor` does the work for you.
Now that the line item has been enriched with the bundle information and the product information, the discount has to be added. This happens in the function `addDiscount`:

```php
private function addDiscount(LineItem $bundleLineItem, BundleEntity $bundle, SalesChannelContext $context): void
{
    if ($this->getDiscount($bundleLineItem)) {
        return;
    }

    $discount = $this->createDiscount($bundle, $context);

    if ($discount) {
        $bundleLineItem->addChild($discount);
    }
}

private function getDiscount(LineItem $bundle): ?LineItem
{
    return $bundle->getChildren()->get($bundle->getReferencedId() . '-discount');
}
```

Here we first check via `getDiscount` if the discount is already in the bundle as a child. 
If the discount has not yet been added, you can create it using the `createDiscount` function:

```php
private function createDiscount(BundleEntity $bundleData, SalesChannelContext $context): ?LineItem
{
    if ($bundleData->getDiscount() === 0) {
        return null;
    }

    switch ($bundleData->getDiscountType()) {
        case self::DISCOUNT_TYPE_ABSOLUTE:
            $priceDefinition = new AbsolutePriceDefinition(
                $bundleData->getDiscount() * -1, 
                $context->getContext()->getCurrencyPrecision()
            );

            $label = 'Absolute bundle voucher';
            break;

        case self::DISCOUNT_TYPE_PERCENTAGE:
            $priceDefinition = new PercentagePriceDefinition(
                $bundleData->getDiscount() * -1, 
                $context->getContext()->getCurrencyPrecision()
            );

            $label = sprintf('Percental bundle voucher (%s%%)', $bundleData->getDiscount());
            break;

        default:
            throw new \RuntimeException('Invalid discount type.');
    }

    $discount = new LineItem(
        $bundleData->getId() . '-discount',
        self::DISCOUNT_TYPE,
        $bundleData->getId()
    );

    $discount
        ->setPriceDefinition($priceDefinition)
        ->setLabel($label)
        ->setGood(false);

    return $discount;
}
```

In this function you have to distinguish whether your bundle discount is a percentage or absolute discount.
```php

switch ($bundleData->getDiscountType()) {
    case self::DISCOUNT_TYPE_ABSOLUTE:
        // ...
    case self::DISCOUNT_TYPE_PERCENTAGE:
        // ...
    default:
        throw new \RuntimeException('Invalid discount type.');
}
```

Depending on the type of discount, either a `\Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition` or `\Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition` is generated.
Then a new line item is created for the discount and provided with the corresponding information such as `->setPriceDefinition()`, `->setLabel()` and `->setGood()`:

```php
$discount = new LineItem(
    $bundleData->getId() . '-discount',
    self::DISCOUNT_TYPE,
    $bundleData->getId()
);

$discount->setPriceDefinition($priceDefinition)
    ->setLabel($label)
    ->setGood(false);

return $discount;
```

Now all bundle line items are provided with all necessary information and can be processed afterwards in the `process`.


### Implementing the `process`

Implementing the `\Shopware\Core\Checkout\Cart\CartProcessorInterface` requires to implement the `process` function.

In this function it is your task to calculate the prices of the bundle and move the bundle from the previous cart to a new cart.
Moving the bundle into the new cart should prevent line items from sneaking through the cart process that were not considered by anyone.

As before, this is the entire source code for implementing the `\Shopware\Core\Checkout\Cart\CartProcessorInterface` in your bundle plugin and will be explained in more detail below.
```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Checkout\Bundle\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class BundleCartProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    // ...

    /**
     * @var PercentagePriceCalculator
     */
    private $percentagePriceCalculator;

    /**
     * @var AbsolutePriceCalculator
     */
    private $absolutePriceCalculator;
    
    /**
     * @var QuantityPriceCalculator
     */
    private $quantityPriceCalculator;

    public function __construct(
        EntityRepositoryInterface $bundleRepository,
        PercentagePriceCalculator $percentagePriceCalculator,
        AbsolutePriceCalculator $absolutePriceCalculator,
        QuantityPriceCalculator $quantityPriceCalculator
    )
    {
        $this->percentagePriceCalculator = $percentagePriceCalculator;
        $this->absolutePriceCalculator = $absolutePriceCalculator;
        $this->quantityPriceCalculator = $quantityPriceCalculator;
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        // ...
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        // collect all bundle in cart
        $bundleLineItems = $original->getLineItems()
            ->filterType(self::TYPE);

        if (\count($bundleLineItems) === 0) {
            return;
        }

        foreach ($bundleLineItems as $bundleLineItem) {
            // first calculate all bundle product prices
            $this->calculateChildProductPrices($bundleLineItem, $context);

            // after the product prices calculated, we can calculate the discount
            $this->calculateDiscountPrice($bundleLineItem, $context);

            // at last we have to set the total price for the root line item (the bundle)
            $bundleLineItem->setPrice(
                $bundleLineItem->getChildren()->getPrices()->sum()
            );

            // afterwards we can move the bundle to the new cart
            $toCalculate->add($bundleLineItem);
        }
    }

    private function getDiscount(LineItem $bundle): ?LineItem
    {
        return $bundle->getChildren()->get($bundle->getReferencedId() . '-discount');
    }

    private function calculateChildProductPrices(LineItem $bundleLineItem, SalesChannelContext $context): void
    {
        $products = $bundleLineItem->getChildren()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        foreach ($products as $product) {
            /** @var QuantityPriceDefinition $priceDefinition */
            $priceDefinition = $product->getPriceDefinition();

            $product->setPrice(
                $this->quantityPriceCalculator->calculate($priceDefinition, $context)
            );
        }
    }

    private function calculateDiscountPrice(LineItem $bundleLineItem, SalesChannelContext $context): void
    {
        $discount = $this->getDiscount($bundleLineItem);

        if (!$discount) {
            return;
        }

        $childPrices = $bundleLineItem->getChildren()
            ->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE)
            ->getPrices();

        $priceDefinition = $discount->getPriceDefinition();

        if (!$priceDefinition) {
            return;
        }

        switch (\get_class($priceDefinition)) {
            case AbsolutePriceDefinition::class:
                $price = $this->absolutePriceCalculator->calculate(
                    $priceDefinition->getPrice(),
                    $childPrices,
                    $context,
                    $bundleLineItem->getQuantity()
                );
                break;

            case PercentagePriceDefinition::class:
                $price = $this->percentagePriceCalculator->calculate(
                    $priceDefinition->getPercentage(),
                    $childPrices,
                    $context
                );
                break;

            default:
                throw new \RuntimeException('Invalid discount type.');
        }

        $discount->setPrice($price);
    }
}
```

First look at the function signature of the `process` function:

```php
public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
``
The following data is transferred here:

* `CartDataCollection $data` - This is the data collection you have enriched with data. 
* `Cart $original` - This is the basket containing the bundle line items you have enriched. This cart will be destroyed after the calculation process. 
* `Cart $toCalculate` - This cart is to be regarded as workspace. It contains all already calculated elements, which were calculated by processors running before your processor. 
* `SalesChannelContext $context` - The known SalesChannelContext where the current global state is located (currency, language, sales channel, customer, etc.) 
* `CartBehavior $behavior` - The `\Shopware\Core\Checkout\Cart\CartBehavior` contains further parameters for the cart. For example, it indicates whether it is a new calculation of an order that has already been placed.

Now it is your task to calculate your bundle line items from the `Cart $original` and add them to the new `Cart $toCalculate` cart so that they remain in the cart.
First of all you have to filter the bundle line items out of the cart again:

```php

// collect all bundle in cart
$bundleLineItems = $original->getLineItems()->filterType(self::TYPE);
```

If there are no bundles in your cart again, you can exit the function early.
```php
if (\count($bundleLineItems) === 0) {
    return;
}
```

Next you iterate the line items to calculate them and add them to the new cart:
```php
foreach ($bundleLineItems as $bundleLineItem) {
    // ...

    // afterwards we can move the bundle to the new cart
    $toCalculate->add($bundleLineItem);
}
```

In order to calculate the bundle completely, the following calculations must take place:
1. first the products of the bundle must be calculated (`calculateChildProductPrices`)
2. then the discount of the bundle can be calculated (`calculateDiscountPrice`)
3. last the total price of the bundle must be given to the bundle line item (`$bundleLineItem->setPrice(...)`)

```php
foreach ($bundleLineItems as $bundleLineItem) {
    // first calculate all bundle product prices
    $this->calculateChildProductPrices($bundleLineItem, $context);

    // after the product prices calculated, we can calculate the discount
    $this->calculateDiscountPrice($bundleLineItem, $context);

    // at last we have to set the total price for the root line item (the bundle)
    $bundleLineItem->setPrice(
        $bundleLineItem->getChildren()->getPrices()->sum()
    );

    // afterwards we can move the bundle to the new cart
    $toCalculate->add($bundleLineItem);
}
```

First have a look at how the products of the bundle are calculated:

```php
private function calculateChildProductPrices(LineItem $bundleLineItem, SalesChannelContext $context): void
{
    $products = $bundleLineItem->getChildren()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);

    foreach ($products as $product) {
        /** @var QuantityPriceDefinition $priceDefinition */
        $priceDefinition = $product->getPriceDefinition();

        $product->setPrice(
            $this->quantityPriceCalculator->calculate($priceDefinition, $context)
        );
    }
}
```

Here you first filter the children of the bundle line item to the type `\Shopware\Core\Checkout\Cart\LineItem\LineItem::PRODUCT_LINE_ITEM_TYPE`.

Since these line items were enriched with data by the `\Shopware\Core\Content\Product\Product\Product\Cart\ProductCartProcessor` you can now use `getPriceDefinition()` to access the price definition of the product.

The return value contains now a `\Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition`, which can be easily calculated using the corresponding `\Shopware\Core\Checkout\Cart\Cart\Price\QuantityPriceCalculator`.
```
$this->quantityPriceCalculator->calculate($priceDefinition, $context)
```

Now that the products have been calculated, you can calculate the discount using the `calculateDiscountPrice()` function.

```php
private function calculateDiscountPrice(LineItem $bundleLineItem, SalesChannelContext $context): void
{
    $discount = $this->getDiscount($bundleLineItem);

    if (!$discount) {
        return;
    }

    $childPrices = $bundleLineItem->getChildren()
        ->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE)
        ->getPrices();

    $priceDefinition = $discount->getPriceDefinition();

    if (!$priceDefinition) {
        return;
    }

    switch (\get_class($priceDefinition)) {
        case AbsolutePriceDefinition::class:
            $price = $this->absolutePriceCalculator->calculate(
                $priceDefinition->getPrice(),
                $childPrices,
                $context,
                $bundleLineItem->getQuantity()
            );
            break;

        case PercentagePriceDefinition::class:
            $price = $this->percentagePriceCalculator->calculate(
                $priceDefinition->getPercentage(),
                $childPrices,
                $context
            );
            break;

        default:
            throw new \RuntimeException('Invalid discount type.');
    }

    $discount->setPrice($price);
}
```

Since a discount is a price which is calculated on the basis of other prices, the taxes of such a discount must also be calculated proportionately.
If you do not want to do this yourself, you can simply use one of the two calculators from the core:
* `\Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator` Calculates prices based on a percentage value relative to the discounting prices.
* `\Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator` Calculates prices based on an absolute price relative to the discounting prices.

However, in order to calculate the taxes proportionately, both calculators need to transfer a `\Shopware\Core\Framework\Pricing\PriceCollection` in which the prices to be discounted are located.
In your case it is the prices of the products that are in the bundle line item stored as children. 
You can easily extract them by first filtering on the product type and then calling `getPrices()`:

```php
$childPrices = $bundleLineItem->getChildren()
    ->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE)
    ->getPrices();
```

The corresponding calculator is then called depending on the discount type (absolute or percentage):

```php
switch (\get_class($priceDefinition)) {
    case AbsolutePriceDefinition::class:
        $price = $this->absolutePriceCalculator->calculate(
            $priceDefinition->getPrice(),
            $childPrices,
            $context,
            $bundleLineItem->getQuantity()
        );
        break;

    case PercentagePriceDefinition::class:
        $price = $this->percentagePriceCalculator->calculate(
            $priceDefinition->getPercentage(),
            $childPrices,
            $context
        );
        break;

    default:
        throw new \RuntimeException('Invalid discount type.');
}

$discount->setPrice($price);
```

If it is a `\Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition`, you call the `\Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator`:
```php
$price = $this->percentagePriceCalculator->calculate(
    $priceDefinition->getPercentage(), 
    $childPrices, 
    $context
);
```

If it is a `\Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition`, you call the `\Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator`.
This even allows you to pass a `quantity` as a fourth parameter to define how often this discount should be fended off. 
```php
$price = $this->absolutePriceCalculator->calculate(
    $priceDefinition->getPrice(),
    $childPrices,
    $context,
    $bundleLineItem->getQuantity()
);
```

Now that all product prices and the discount have been calculated, you only have to calculate the total price of the bundle and then transfer the bundle to the new shopping cart:
```php
public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
{
    // ... 
    foreach ($bundleLineItems as $bundleLineItem) {
        // ... 

        // at last we have to set the total price for the root line item (the bundle)
        $bundleLineItem->setPrice(
            $bundleLineItem->getChildren()->getPrices()->sum()
        );

        // afterwards we can move the bundle to the new cart
        $toCalculate->add($bundleLineItem);
    }
}
```

Your plugin is almost done, just some last polishing is necessary. Head over to the [next step](./100-final-preparation.md) for the last few changes necessary.
