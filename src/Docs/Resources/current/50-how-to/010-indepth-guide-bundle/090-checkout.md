[titleEn]: <>(Step 9: Checkout logic)
[hash]: <>(article:bundle_checkout)

You're already putting a bundle into the cart, or at least you created a new line item with a custom type `swagbundle`.
And that's the scenario given for this chapter, which will be all about properly handling this new line item type and therefore changing the cart's behavior.

## Creating a cart processor/collector

To implement an extension for the cart you have to implement two interfaces:
- `\Shopware\Core\Checkout\Cart\CartDataCollectorInterface`
- `\Shopware\Core\Checkout\Cart\CartProcessorInterface`

What are these classes for?

With the `CartDataCollectorInterface` you can enrich your cart with further data. This interface's `collect` method is the right spot to fetch data using the DAL repositories.
Implementing the `CartProcessorInterface`, you can intervene in the calculation process of the cart. For example, you can access subtotals here.
This is where you're going to calculate the price for your bundle, using the prices of the products of your bundle.

Why are these two processes separated from each other?

As you will notice later, the separation of these two processes offers a serious advantage.
For example, you can control more precisely when you want to enrich data and when you want to calculate prices, you don't have to do all this in just one class.

Those classes affect the cart process, hence they will reside in the directory `<plugin root>/src/Core/Checkout/Bundle/Cart/`.
This is up for change and only dependant on the namespace you're using and the way you registered those classes.
The latter will be explained later in this tutorial as well, don't worry.

### Implementing the `collect` function

Create a new file `BundleCartProcessor.php` in the directory mentioned above, create the respective class `BundleCartProcessor`,
and have it implement the `CartDataCollectorInterface`.

As described above, your task here is to enrich the bundle line items in the cart with information, since right now there's only a generic empty line item in the cart with the type `swagbundle`.
Below is the complete source code for implementing your `CartDataCollectorInterface` for your bundle plugin.
It is explained in detail below the sourcecode.

```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Checkout\Bundle\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\QuantityInformation;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Swag\BundleExample\Core\Content\Bundle\BundleCollection;
use Swag\BundleExample\Core\Content\Bundle\BundleEntity;

class BundleCartProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    public const TYPE = 'swagbundle';
    public const DISCOUNT_TYPE = 'swagbundle-discount';
    public const DATA_KEY = 'swag_bundle-';
    public const DISCOUNT_TYPE_ABSOLUTE = 'absolute';
    public const DISCOUNT_TYPE_PERCENTAGE = 'percentage';

    /**
     * @var EntityRepositoryInterface
     */
    private $bundleRepository;

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
    ) {
        $this->bundleRepository = $bundleRepository;
        $this->percentagePriceCalculator = $percentagePriceCalculator;
        $this->absolutePriceCalculator = $absolutePriceCalculator;
        $this->quantityPriceCalculator = $quantityPriceCalculator;
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        /** @var LineItemCollection $bundleLineItems */
        $bundleLineItems = $original->getLineItems()->filterType(self::TYPE);

        // no bundles in cart? exit
        if (\count($bundleLineItems) === 0) {
            return;
        }

        // fetch missing bundle information from database
        $bundles = $this->fetchBundles($bundleLineItems, $data, $context);

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

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        // collect all bundle in cart
        /** @var LineItemCollection $bundleLineItems */
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

        $bundleProducts = $bundle->getProducts();
        if ($bundleProducts === null) {
            throw new \RuntimeException(sprintf('Bundle "%s" has no products', $bundle->getName()));
        }

        $firstBundleProduct = $bundleProducts->first();
        if ($firstBundleProduct === null) {
            throw new \RuntimeException(sprintf('Bundle "%s" has no products', $bundle->getName()));
        }

        $firstBundleProductDeliveryTime = $firstBundleProduct->getDeliveryTime();
        if ($firstBundleProductDeliveryTime !== null) {
            $firstBundleProductDeliveryTime = DeliveryTime::createFromEntity($firstBundleProductDeliveryTime);
        }

        $bundleLineItem->setRemovable(true)
            ->setStackable(true)
            ->setDeliveryInformation(
                new DeliveryInformation(
                    $firstBundleProduct->getStock(),
                    (float) $firstBundleProduct->getWeight(),
                    (bool) $firstBundleProduct->getShippingFree(),
                    $firstBundleProduct->getRestockTime(),
                    $firstBundleProductDeliveryTime
                )
            )
            ->setQuantityInformation(new QuantityInformation());
    }

    private function addMissingProducts(LineItem $bundleLineItem, BundleEntity $bundle): void
    {
        $bundleProducts = $bundle->getProducts();
        if ($bundleProducts === null) {
            throw new \RuntimeException(sprintf('Bundle %s has no products', $bundle->getName()));
        }

        foreach ($bundleProducts->getIds() as $productId) {
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
        if ($bundleData->getDiscount() === 0.0) {
            return null;
        }

        switch ($bundleData->getDiscountType()) {
            case self::DISCOUNT_TYPE_ABSOLUTE:
                $priceDefinition = AbsolutePriceDefinition::create($bundleData->getDiscount() * -1);
                $label = 'Absolute bundle voucher';
                break;

            case self::DISCOUNT_TYPE_PERCENTAGE:
                $priceDefinition = PercentagePriceDefinition::create($bundleData->getDiscount() * -1);
                $label = sprintf('Percentual bundle voucher (%s%%)', $bundleData->getDiscount());
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

    private function calculateChildProductPrices(LineItem $bundleLineItem, SalesChannelContext $context): void
    {
        /** @var LineItemCollection $products */
        $products = $bundleLineItem->getChildren()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        foreach ($products as $product) {
            $priceDefinition = $product->getPriceDefinition();
            if ($priceDefinition === null || !$priceDefinition instanceof QuantityPriceDefinition) {
                throw new \RuntimeException(sprintf('Product "%s" has invalid price definition', $product->getLabel()));
            }

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

First you check if there is a bundle in your cart. For this you can use the `filterType` method of the `LineItemCollection`.
```php
// collect all bundle in cart
$bundleLineItems = $original->getLineItems()->filterType('swagbundle');
``` 

If there are no bundles in your cart, you can already exit here, there's nothing to do for your plugin.
```php
// no bundles in cart? exit
if (\count($bundleLineItems) === 0) {
    return;
}
```

For the cart bundles you have to fetch the corresponding information from the database and add the information into the `CartDataCollection`.
Each bundle in the cart gets its own entry in the `CartDataCollection`:

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
In order to prevent fetching the data unnecessarily often from the database, you must check whether the data is not already set in the `CartDataCollection`.
</p>

```php
/**
 * Fetches all Bundles that are not already stored in the CartDataCollection
 */
private function fetchBundles(LineItemCollection $bundleLineItems, CartDataCollection $data, SalesChannelContext $context): BundleCollection
{
    $bundleIds = $bundleLineItems->getReferenceIds();

    $filtered = [];
    foreach ($bundleIds as $bundleId) {
        // If data already contains the bundle, we don't need to fetch it again
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
The `Criteria` object takes an optional parameter, an array of IDs, which is quite helpful here.
Since the bundle line items may not yet contain the bundle's assigned products, they must also be fetched from the database:
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
* `enrichBundle` Here you enrich the bundle line item itself with information (label, quantity, delivery, etc.).
* `addMissingProducts` Here you add the missing products of the bundle, as a child line item.
* `addDiscount` Lastly the discount item is added to the bundle, again as a child line item.

Those method names are freely chosen and implemented to structure the code a little bit better.

The `swagbundle` line item is the "parent" line item in this plugin, and all assigned products and the discount itself
are added as child items. This will be important when it comes to the price calculation, since the bundle's price is just
a sum of all child prices.

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
* `$bundleLineItem->setLabel(...)` The label for the bundle is set
* `$bundleLineItem->setRemovable(true)` Using `setRemovable`, you define that the customer can remove the bundle from the cart by himself
* `$bundleLineItem->setStackable(true)` By marking it as `stackable`, you define that the customer may change the quantity in the cart.
* `$bundleLineItem->setDeliveryInformation(...)` Here you set the information for distribution into a delivery. For the sake of simplicity, the information of the first product is simply used here.
* `$bundleLineIten->setQuantityInformation()` You can use the `Shopware\Core\Checkout\Cart\LineItem\QuantityInformation` to define minimum and maximum orders.

Now that you have added all the information to the bundle line item, the bundle's products must be added to the bundle using the `addMissingProducts` method:
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

In order to add the products now, you iterate over all products of the `BundleEntity $bundle` and check if it is a child already.

```php
foreach ($bundle->getProducts()->getIds() as $productId) {
    // if the bundleLineItem already contains the product we can skip it
    if ($bundleLineItem->getChildren()->has($productId)) {
        continue;
    }

    // ...
}
``` 

If the product was not added as a child yet, you can simply add a new `LineItem` with the type `\Shopware\Core\Checkout\Cart\LineItem\LineItem::PRODUCT_LINE_ITEM_TYPE`.

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

If you register your `CartDataCollectorInterface` **before** the core `ProductCartProcessor`, the core will take care
that the `LineItem` of type `LineItem::PRODUCT_LINE_ITEM_TYPE`, added by you, is enriched with the required information.
You don't have to take care of all the product information like: price, label, cover, delivery information, quantity information.
The `ProductCartProcessor` does the work for you, just make sure your plugin's processor runs prior to it.
Now that the line item has been enriched with the bundle information and will receive the product information later, the discount has to be added. This happens in the method `addDiscount`:

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

Here we first check via `getDiscount` if the discount is already a child of the bundle.
If the discount has not yet been added, it is created using the `createDiscount` method:

```php
private function createDiscount(BundleEntity $bundleData, SalesChannelContext $context): ?LineItem
{
    // The bundle has no discount, no need to add anything then
    if ($bundleData->getDiscount() === 0) {
        return null;
    }

    switch ($bundleData->getDiscountType()) {
        case self::DISCOUNT_TYPE_ABSOLUTE:
            $priceDefinition = AbsolutePriceDefinition::create($bundleData->getDiscount() * -1);

            $label = 'Absolute bundle voucher';
            break;

        case self::DISCOUNT_TYPE_PERCENTAGE:
            $priceDefinition = PercentagePriceDefinition::create($bundleData->getDiscount() * -1);

            $label = sprintf('Percentual bundle voucher (%s%%)', $bundleData->getDiscount());
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

In this method you have to distinguish whether your bundle discount is a percentage or an absolute discount.
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
Then a new line item is created for the discount and provided with the corresponding information, using the methods `setPriceDefinition`, `setLabel` and `setGood`:

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

For those of you wondering about the method `setGood`:
The method `setGood` marks a line item as a `good` if it's set to `true`. This is necessary for properly filtering the line items
for e.g. a rule of the rule system.
Imagine a rule "Cart discount", which applies a 10€ discount if the cart's sum is more than 100.
Now imagine you'd have a cart sum of 102€, the discount would be applied and subtract 10€.
In a next iteration, the rule's validity would be checked again and the rule system would notice, that the discount is no longer valid,
since the cart's sum is only 92€. The discount would be removed, which raises the sum to 102€ again.
This way, the discount would be added, only to then invalidate it's own rule.
The solution is to only consider goods for this calculation.
And no, you can't use `LineItem::PRODUCT_LINE_ITEM_TYPE` for this, since there might be custom line item types, which are to be considered
a good as well. Such as the bundle itself.

Now all bundle line items are enriched with all necessary information and can be processed afterwards in the `process`.

### Implementing the `process`

Implementing the `\Shopware\Core\Checkout\Cart\CartProcessorInterface` requires to implement the `process` function.

In this method, it is your task to calculate the prices of the bundle and move the bundle from the previous cart to a new cart.
But what does that even mean? The `process` method receives two instances of a cart.
The first one `Cart $original` contains all the original information, such as your bundle line item and all its children.
The second instance, `Cart $toCalculate`, is actually an empty cart instance, only containing line items that were added from previous **processors**, not collectors, already. It is the one, which will be used when all the calculating is done.
This prevents line items from sneaking through the cart process that were not considered by any processor and thus will be automatically dropped.

Of course, this also means, that you have to add your bundle line item to the `$toCalculate` cart again.

As before, this is the entire source code for implementing the `CartProcessorInterface` in your bundle plugin and will be explained in more detail below.
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

First look at the method signature of the `process` function:

```php
public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
```
The following data is transferred here:

* `CartDataCollection $data` - This is the data collection you have enriched with data previously in the `collect` method.
* `Cart $original` - This is the basket containing the bundle line items you have enriched. This cart will be destroyed after the calculation process. 
* `Cart $toCalculate` - This cart is to be regarded as workspace. It contains all already calculated elements, which were calculated by processors running before your processor. 
* `SalesChannelContext $context` - The known SalesChannelContext where the current global state is located (currency, language, sales channel, customer, etc.) 
* `CartBehavior $behavior` - The `\Shopware\Core\Checkout\Cart\CartBehavior` contains further parameters for the cart. For example, it indicates whether it is a new calculation of an order that has already been placed.

Now it is your task to calculate your bundle line items from the `Cart $original` and add them to the new `Cart $toCalculate` cart so that they remain in the cart.
First of all you have to filter the bundle line items out of the cart again:

```php

// collect all bundles of the cart
$bundleLineItems = $original->getLineItems()->filterType(self::TYPE);
```

If there are no bundles in your cart, you can exit the method early.
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
1st: The products of the bundle must be calculated (`calculateChildProductPrices`)
2nd: Then the discount of the bundle can be calculated (`calculateDiscountPrice`)
3rd: Last the total price of the bundle must be given to the bundle line item (`$bundleLineItem->setPrice(...)`)

```php
foreach ($bundleLineItems as $bundleLineItem) {
    // first calculate all bundle product prices
    $this->calculateChildProductPrices($bundleLineItem, $context);

    // after the product prices are calculated, we can calculate the discount
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

First, you filter the children of the bundle line item to the type `LineItem::PRODUCT_LINE_ITEM_TYPE`.
Since these line items were enriched with data by the `ProductCartProcessor` already, you can now use `getPriceDefinition()` to access the price definition of the product.

The return value now contains a `\Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition`, which can be easily calculated using the corresponding `\Shopware\Core\Checkout\Cart\Cart\Price\QuantityPriceCalculator`.
```
$this->quantityPriceCalculator->calculate($priceDefinition, $context)
```

Now that the products have been calculated, you can calculate the discount's value using the `calculateDiscountPrice()` method.

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

However, in order to calculate the taxes proportionately, both calculators need to have a `\Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection` in which the prices to be discounted are located.
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

If it is a `PercentagePriceDefinition`, you call the `PercentagePriceCalculator`:
```php
$price = $this->percentagePriceCalculator->calculate(
    $priceDefinition->getPercentage(), 
    $childPrices, 
    $context
);
```

If it is a `AbsolutePriceDefinition`, you call the `AbsolutePriceCalculator`.
This even allows you to pass a `quantity` as a fourth parameter to define how often this discount should be fended off. 
```php
$price = $this->absolutePriceCalculator->calculate(
    $priceDefinition->getPrice(),
    $childPrices,
    $context,
    $bundleLineItem->getQuantity()
);
```

Now that all product prices and the discount have been calculated, you only have to calculate the total price of the bundle and then transfer the bundle to the new shopping cart.
The bundle's price is just a sum of all of it's children.
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

## Registering your processor

That's a lot of theory and a lot of code - but you didn't register your new processor yet.
Let's quickly add them in the `services.xml` again:
```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
    
    <services>
        ...

        <service id="Swag\BundleExample\Core\Checkout\Bundle\Cart\BundleCartProcessor">
            <argument type="service" id="swag_bundle.repository"/>
            <argument type="service" id="Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator"/>
            <argument type="service" id="Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator"/>
            <argument type="service" id="Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator"/>

            <!-- inject before product processor (5000) -->
            <tag name="shopware.cart.processor" priority="6000" />
            <tag name="shopware.cart.collector" priority="6000" />
        </service>
    </services>
</container>
```

The necessary calculators are injected into your processor. Note the tags though, `shopware.cart.processor` and `shopware.cart.collector`.
The `priority` defines the order they are executed and as you might remember, your processor has to run before the `ProductCartProcessor`.

Your plugin is almost done, just some last polishing is necessary. Head over to the [next step](./100-final-preparation.md) for the last few changes necessary.
