[titleEn]: <>(Step 9: Checkout logic)

You're already putting a bundle into the cart, or at least you created a new line item with a custom type `swagbundle`.
And that's the scenario given for this chapter, which will be all about properly handling this new line item type and therefore changing the cart's behavior

### Creating a cart collector

Changing the cart's behavior is accomplished by using Shopware 6's enrichment pattern.
You're registering a new service using the `shopware.cart.collector` tag and you'll have your class implement the `Shopware\Core\Checkout\Cart\CollectorInterface` interface.
A `CartCollector` will be executed every time the cart changes, due to quantity changes, new items in the cart, etc.

The interface requires you to implement three methods for the enrichment process, which will be explained in short here:
<dl>
    <dt>prepare</dt>
    <dd>
        This one will be executed first. Prepare your set of line items here. The most common thing here to do, is to filter all line item's for your specific
        type and add them into a custom `FetchDefinition`. This way, you can ensure your collector is only taking care of those line items he actually supports.
    </dd>
    
    <dt>collect</dt>
    <dd>
        As the name suggests, collect all the necessary data for your line items here. This is the only method, where you should execute database actions!
        You can also add new `FetchDefinitions`, such as a `ProductFetchDefinition` to have a product line item being enriched by the `ProductCollector` to be used in your collector later on.
    </dd>
    
    <dt>enrich</dt>
    <dd>
        This is where the line item is actually enriched with previously collected data. You're also adding discounts and prices here.
    </dd>
</dl>

Enough of the theory, time to actually get started. 
Just like in the core, you're going to use the the following directory structure: `<bundle source root>/Core/<Domain>/Cart/`
For this plugin, the following structure is used: `<plugin root>/src/Core/Checkout/Bundle/Cart/BundleCollector.php`

Go ahead and create this class and, as previously mentioned, implement the interface `Shopware\Core\Checkout\Cart\CollectorInterface`.

```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Checkout\Bundle\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CollectorInterface;
use Shopware\Core\Framework\Struct\StructCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class BundleCollector implements CollectorInterface
{
    public function prepare(StructCollection $definitions, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        
    }

    public function collect(StructCollection $fetchDefinitions, StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        
    }

    public function enrich(StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        
    }
}
```

### Prepare the cart

Starting with the `prepare` method, what should be done here? You've added the line item using a specific type, it was `swagbundle` if you remember.
You could now fetch all line items of this type and add only those to some kind of a custom collection, so the next method `collect` can continue working on just
those line items. Each "cart collector" comes with a so called `FetchDefinition`, which basically only contains an array of ids.
Therefore, each collector can make sure he's only taking care of the line items, that he actually supports.
So, before the actual `prepare` method's code will be implemented, create your custom `FetchDefinition` the same directory.
All it has to do is to contain an array of ID's, applied upon instantiating your `FetchDefinition`.

```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Checkout\Bundle\Cart;

use Shopware\Core\Framework\Struct\Struct;

class BundleFetchDefinition extends Struct
{
    /**
     * @var string[]
     */
    protected $ids;

    /**
     * @param string[] $ids
     */
    public function __construct(array $ids)
    {
        $this->ids = $ids;
    }

    /**
     * @return string[]
     */
    public function getIds(): array
    {
        return $this->ids;
    }
}
```

Now, how do you filter the cart for your specific line items? You might have noticed, that the `prepare` method's second parameter actually contains the current
[cart struct](https://github.com/shopware/platform/blob/master/src/Core/Checkout/Cart/Cart.php). The cart struct contains all line items and also brings
a method to return a [LineItemCollection](https://github.com/shopware/platform/blob/master/src/Core/Checkout/Cart/LineItem/LineItemCollection.php). 
Having a look at this `LineItemCollection`, you'll eventually find the method `filterType`. That sounds perfect for your use-case, doesn't it?
It also returns another `LineItemCollection`, only containing your filtered line items, so you can apply even more filtering afterwards or use any of the
other helper methods.
You can use this method to filter all line items by a given type, `swagbundle` in your case. Also, add this string as a constant, so a third-party collector
could also use your type and filter for bundles themselves.
When there's not a single line item using this type, simply do nothing. Otherwise, create a new instance of your `BundleFetchDefinition`.
It asks for an array of IDs, so you could now iterate through all filtered bundle line items and collect the IDs in an array - or you just use
the `LineItemCollection`'s helper method `getKeys` for it.
*Note: If you're desperately looking for the `geyKeys` method in the `LineItemCollection` now, you'll find it in the abstract class `Collection`. The `LineItemCollection`
extends from the `Collection` and thus also knows this method.

So, you've got your custom `FetchDefinition` and it even contains only your bundle line item's keys, but where to put this data now?
You might have noticed the first parameter `StructCollection $definitions` already. Add it to this collection using the `add` method.

```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Checkout\Bundle\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CollectorInterface;
use Shopware\Core\Framework\Struct\StructCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class BundleCollector implements CollectorInterface
{
    public const TYPE = 'swagbundle';
    
    public function prepare(StructCollection $definitions, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $bundleLineItems = $cart->getLineItems()->filterType(self::TYPE);

        if ($bundleLineItems->count() === 0) {
            return;
        }

        $definitions->add(new BundleFetchDefinition($bundleLineItems->getKeys()));
    }
}
```

### Collecting the data

The first thing to do, is to fetch the previously added `FetchDefinition`. Once again, the first parameter is the said `StructCollection`. It does not only
come with an `add` method, but also with a method to get your custom `FetchDefinition`.

```php
public function collect(StructCollection $fetchDefinitions, StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
{
    $bundleDefinitions = $fetchDefinitions->filterInstance(BundleFetchDefinition::class);
    
    if ($bundleDefinitions->count() === 0) {
        return;
    }
}
```

Via the method `filterInstance`, you can use your custom fetch definition's class to get all `BundleFetchDefinitions`, that were added to this collection.
You're also checking if any `FetchDefinition` from your bundle is available at all, since the `collect` method has no idea about whether or not the `prepare` added
something or not. Furthermore, what happens if another third party plugin's `prepare` method, which might have been executed after your own `prepare` method, removed
your `BundleFetchDefinition` from the collection again?
This way, you're only proceeding if there's still a `BundleFetchDefinition` available.

Now you want to fulfill the `collect` method's main purpose: Collecting the necessary data. In order to collect bundle data, you will need access
to the bundle repository, so add the bundle repository using the DI container:

```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Checkout\Bundle\Cart;

use Shopware\Core\Checkout\Cart\CollectorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class BundleCollector implements CollectorInterface
{
    public const TYPE = 'swagbundle';

    /**
     * @var EntityRepositoryInterface
     */
    private $bundleRepository;

    public function __construct(EntityRepositoryInterface $bundleRepository)
    {
        $this->bundleRepository = $bundleRepository;
    }
    ...
}
```

Talking about the DI container, good time to even define your collector in the `services.xml` using the DI tag `shopware.cart.collector`.

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
    
    <services>
        ...
        
        <service id="Swag\BundleExample\Core\Checkout\Bundle\Cart\BundleCollector">
            <argument type="service" id="swag_bundle.repository"/>
            <tag name="shopware.cart.collector"/>
        </service>
    </services>
</container>
```

Now you can use the `search` method on the repository to find the bundles by their IDs, which are inside of the `FetchDefinitions`.
The `search` method asks for a `Criteria` object, which in return takes the IDs as a constructor parameter.

In order to properly fetch all IDs, you have to iterate through all available `BundleFetchDefinitions`, even though this will most likely only be a single one,
you can never guarantee this.
Just iterate through the fetch definitions, create a flat unique array containg all the bundle IDs and apply them to a `Criteria` object, which will then be used
for the `search` method of the bundle's repository. 

```php
public function collect(StructCollection $fetchDefinitions, StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
{
    $bundleDefinitions = $fetchDefinitions->filterInstance(BundleFetchDefinition::class);

    if ($bundleDefinitions->count() === 0) {
        return;
    }

    $ids = [[]];
    /** @var BundleFetchDefinition $fetchDefinition */
    foreach ($bundleDefinitions as $fetchDefinition) {
        $ids[] = $fetchDefinition->getIds();
    }

    // Create a flattened array of unique IDs
    $ids = array_unique(array_merge(...$ids));

    $criteria = new Criteria($ids);
    $bundles = $this->bundleRepository->search($criteria, $context->getContext())->getEntities();
}
```

The variable `$bundles` now contains your `BundleCollection` you've created way earlier in this tutorial.

So, you've collected the bundle data for all the bundle's being used in the current cart.

Let's have a bit of theory on what to do next.
The cart in Shopware 6 supports nested line items, which means, that a line item can contain multiple other line items as "childs".
This sounds perfect for a bundle: Have a parent line item as the "bundle" item, and its childrens being all the products that are part of this bundle.
As of now, there's only a single line item in the cart, so you have to take of adding child line item for each product assigned to the bundle.
This also means, that you need the `products` association on the bundles again, so add this to the `Criteria` object now.

```php
$criteria = new Criteria($ids);
$criteria->addAssociation('products');
$bundles = $this->bundleRepository->search($criteria, $context->getContext())->getEntities();
```

In short, these are the steps you need to do now:
- Iterate through all bundles currently being used in the cart
- Fetch the instance of the bundle `LineItem` in the cart, so you can add child line items to it
- Iterate through all products of a bundle and for each product, create a new line item of the type 'product' and add it to the main bundle line item

This is where a big advantage of the enrichment process takes place: 
You can make sure to have your `collect` method being executed **before** the `ProductCollector`.
This way, you can add another `ProductFetchDefinition` at the end of your `collect` method, which will then be considered by the `ProductCollector`'s `collect´ method.
You need to do this, so the products inside your bundle's line item will be enriched in the process as well.

In order to have your collector executed earlier in the process, add a `priority` to your service's tag.
```xml
<service id="Swag\BundleExample\Core\Checkout\Bundle\Cart\BundleCollector">
    <argument type="service" id="swag_bundle.repository"/>
    <tag name="shopware.cart.collector" priority="1000"/>
</service>
```
The higher the priority, the earlier this tagged service will be considered.

The `ProductFetchDefinition` will also need an array of all IDs of the products being used in the cart, so make sure to collect those IDs while iterating
through the products for your bundle.

Enough text, here's some example code:
```php
public function collect(StructCollection $fetchDefinitions, StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
{
    ...
    $bundles = $this->bundleRepository->search($criteria, $context->getContext())->getEntities();
    $productIds = [[]];
    /** @var BundleEntity $bundle */
    foreach ($bundles as $bundle) {
        $productIds[] = $bundle->getProducts()->getIds();
    
        $bundleLineItem = $cart->get($bundle->getId());
    
        if (!$bundleLineItem) {
            continue;
        }
    
        // Add line items BEFORE collect and enrich of the product entity
        foreach ($bundle->getProducts()->getIds() as $productId) {
            if ($bundleLineItem->getChildren()->has($productId)) {
                continue;
            }
            $productLineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE);
            $productLineItem->setPayload(['id' => $productId]);
            $bundleLineItem->addChild($productLineItem);
        }
    
        $bundleLineItem->setRemovable(true)->setStackable(true);
    }
    
    // Flatten array
    $productIds = array_merge(...$productIds);
}
```

So, you're iterating through each bundle. While doing that, you're immediately collecting all available product IDs to be applied to the `ProductFetchDefinition` later on.
Then, you fetch the actual instance of the bundle line item by using the `get` method on the cart struct. If there's no bundle line item with this ID, do nothing.
Afterwards you iterate through all products of a bundle, so you can create a new line item of type 'product' and add it as a child to the bundle line item.

Two more things are added here already:
1st: The condition if the current bundle line item already knows a child. There's no need to add a product line item as a child twice to a bundle, if it was added previously already.
This is due to the whole enrichment process being executed each time the cart is updated. The child line item is added in the moment you add the bundle from the detail page.
If you add another product to the cart now, the whole process is executed again. Without the condition, your bundle line item would receive more and more child line items each time the cart is updated.

2nd: The product line items work by providing an ID as a payload. You can't rely on the line item's ID here, since this could be anything at this point, not just
a product or a product's ID. Just like in this example, the bundle ID is used for the line item's ID. You can also add a line item without providing any given ID, so it is
automatically generated. Thus, the payload is the only reliable source to provide an actual ID of the product entity.
*Note: Yes, your `BundeCollector` does rely on the line item ID to fetch a bundle here. This is due to the fact, that you will most likely be the only one
to add a bundle line item to the cart and you made sure to set the ID in your template. If you're unsure about this, go ahead and work with the payload as well.
Make sure to also set this payload in your `prepare` method then.*

Also, you're defining your bundle line item as "stackable" as well as "removable". If a customer puts your bundle into the cart twice, it will have a quantity of 2.

Two more lines of code are still necessary here.
First of all, you need to apply the `$productIds` to a new `ProductFetchDefinition`, so the `ProductCollector` will take care of collecting the data for your 
bundle's child item.
This `FetchDefinition` then has to be added to the `StructCollection` again.
The last thing to do in your `collect` method is to make sure your collected bundle data can be used in the `enrich` method.
For this purpose, there's the second parameter of the `prepare` method, another `StructCollection` called `$data`. This one will be available in the `enrich` method,
so add your bundle data to this collection.

This time though, don't add it to the collection using the `add` method, but the `set` method with a given key instead.
But why's that different from how you added `BundleFetchDefinition` to the other `StructCollection` in the `prepare` method?

If some third-party plugin adds bundles to the cart themselves, they can do so by simply providing a `BundleFetchDefinition` themselves and from there on,
your `BundleCollector` will automatically take care of the rest. 
When it comes to the next step though, you do not want the collected bundle data to be manipulated or used by some third party collector.
This data is **only** collected for your collectors `enrich` method to be used and for no one else. Thus, you're adding this data using a unique key, that's not exposed publicly.
Of course, someone could still use this key by looking into your code and using the same key in their custom collector, but then they messed up on intention.

This is how your full `collect` method should look like now:
```php
public function collect(StructCollection $fetchDefinitions, StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
{
    $bundleDefinitions = $fetchDefinitions->filterInstance(BundleFetchDefinition::class);

    if ($bundleDefinitions->count() === 0) {
        return;
    }

    $ids = [[]];
    /** @var BundleFetchDefinition $fetchDefinition */
    foreach ($bundleDefinitions as $fetchDefinition) {
        $ids[] = $fetchDefinition->getIds();
    }

    $ids = array_unique(array_merge(...$ids));

    $criteria = new Criteria($ids);
    $criteria->addAssociation('products');
    $bundles = $this->bundleRepository->search($criteria, $context->getContext())->getEntities();

    $productIds = [[]];
    /** @var BundleEntity $bundle */
    foreach ($bundles as $bundle) {
        $productIds[] = $bundle->getProducts()->getIds();

        $bundleLineItem = $cart->get($bundle->getId());

        if (!$bundleLineItem) {
            continue;
        }

        // Add line items BEFORE collect and enrich of the product entity
        foreach ($bundle->getProducts()->getIds() as $productId) {
            if ($bundleLineItem->getChildren()->has($productId)) {
                continue;
            }
            $productLineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE);
            $productLineItem->setPayload(['id' => $productId]);
            $bundleLineItem->addChild($productLineItem);
        }

        $bundleLineItem->setRemovable(true)->setStackable(true);
    }

    $productIds = array_merge(...$productIds);

    $fetchDefinitions->add(new ProductFetchDefinition($productIds));
    $data->set(self::DATA_KEY, $bundles);
}
```

The constant `DATA_KEY` is a private constant, which only contains the string `swag_bundles`.

### Enriching the bundle

You've collected all the necessary bundle data for the cart, it's time to start actually providing a price for your bundle using this data.
As mentioned already, the first parameter of the `enrich` method will be the `StructCollection` again, also containing your bundle data.
Fetch it using the previously defined constant `DATA_KEY` and the method `get`.

```php
public function enrich(StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
{
    if (!$data->has(self::DATA_KEY)) {
        return;
    }

    /** @var BundleCollection $bundles */
    $bundles = $data->get(self::DATA_KEY);
}
```
*Note: At this moment, the product line items, who are childs of your bundle, have been enriched already. Thus, you know all their data, including the price. 
If you didn't make sure your collector gets executed before the `ProductCollector` is processed, you'd have no clue about the prices here.*

So, what to do with this data now?
Iterate through all bundle line items in the cart again, find their corresponding bundle entry in the `BundleCollection` and start enriching the 
bundle line item. This means setting the bundle line item's label by using the bundle's name, as well as providing a price for your bundle.
A parent line item inherits its price from the children, so you need to add another child line item, which represents the actual discount to be applied.

Let's go through this, starting with iterating through all bundle line items in the cart.
```php
public function enrich(StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
{
    ...

    $bundleLineItems = $cart->getLineItems()->filterType(self::TYPE);
    if (count($bundleLineItems) === 0) {
        return;
    }

    /** @var LineItem $bundleLineItem */
    foreach ($bundleLineItems as $bundleLineItem) {
    }
}
```

Finding the corresponding `BundleEntity` for the current bundle line item.
```php
foreach ($bundleLineItems as $bundleLineItem) {
    $id = $bundleLineItem->getKey();

    $bundle = $bundles->get($id);

    if (!$bundle) {
        continue;
    }
}
```

If there's no `BundleEntity` for the current bundle line item, something went wrong. For stability reasons, do not kill the whole cart process here, just do nothing.

Now start enriching the bundle line item by adding a label to the line item.
```php
foreach ($bundleLineItems as $bundleLineItem) {
    $id = $bundleLineItem->getKey();

    $bundle = $bundles->get($id);

    if (!$bundle) {
        continue;
    }
    
    if (!$bundleLineItem->getLabel()) {
        $bundleLineItem->setLabel($bundle->getName());
    }
}
```

Once again, this is not necessary if this bundle line item was processed once already.

Now add a new discount as a child item.
```php
...
if (!$bundleLineItem->getLabel()) {
    $bundleLineItem->setLabel($bundle->getName());
}
$bundleLineItem->getChildren()->add($this->calculateBundleDiscount($bundleLineItem, $bundle, $context));
```

You might have figured, that you need to create the method `calculateBundleDiscount` now and you eventually figured, that it has to
return an instance of an line item.
It will need the current bundle line item, to figure out the quantity of the bundle. If there's two bundles in the cart, the discount has to
double as well, right?
The `BundleEntity` is also applied, because it contains the mainly necessary discount data, such as the `discountType` and the actual value of the discount.
The last parameter is the context, which contains the currency precision, that you'll also need.

```php
private function calculateBundleDiscount(LineItem $bundleLineItem, BundleEntity $bundleData, SalesChannelContext $context): ?LineItem
{
    
}
```

Start of with checking if the bundle was configured properly. What if the bundle has a discount value of "0"? No need to add any discount then, right?
```php
private function calculateBundleDiscount(LineItem $bundleLineItem, BundleEntity $bundleData, SalesChannelContext $context): ?LineItem
{
    if ($bundleData->getDiscount() === 0) {
        return null;
    }
}
```

For this reason, the return type of the method `calculateBundleDiscount` is also noted as nullable.

So, how do you apply a discount now?
A discount is also a `LineItem`, but it has to come with a custom `PriceDefinition`, hence the method `setPriceDefinition` on an line item.
Available `PriceDefinitions` in Shopware 6 are `AbsolutePriceDefinition` as well as `PercentagePriceDefinition` - perfect!

Depending on the `discountType` of the bundle, you can create or the other. Both of them require the same parameters.
The first one being the actual discount value, the second one being the previously mentioned 'currency precision', which you can find in the provided context.
Depending on the type, you might want to set a custom label of the discount line item, but you could also go for a more general label, such as "Bundle discount". 
In this example, there will be a separate label for each case.

So, now go ahead and create a new respective `PriceDefinition` depending on the bundle entities' `discountType`:
```php
switch ($bundleData->getDiscountType()) {
    case self::DISCOUNT_TYPE_ABSOLUTE:
        $price = new AbsolutePriceDefinition($bundleData->getDiscount() * -1, $context->getContext()->getCurrencyPrecision());
        $label = 'Absolute bundle voucher';
        break;
    
    case self::DISCOUNT_TYPE_PERCENTAGE:
        $price = new PercentagePriceDefinition($bundleData->getDiscount() * -1, $context->getContext()->getCurrencyPrecision());
        $label = sprintf('Percental bundle voucher (%s%%)', $bundleData->getDiscount());
        break;
}
```

You're checking for the type and depending on that, you create one of the two mentioned price definitions. This done using another few constants, just add them like this:
```php
class BundleCollector implements CollectorInterface
{
    public const TYPE = 'swagbundle';
    public const DISCOUNT_TYPE_ABSOLUTE = 'absolute';
    public const DISCOUNT_TYPE_PERCENTAGE = 'percentage';
    private const DATA_KEY = 'swag_bundles';
    
    ...
}
```

Now simply create a new line item for the discount, use the `setPriceDefinition` method to apply the previously created price definition, and apply the label to the line item.
Also add the quantity from the original bundle line item, so the discount actually scales with the quantity.

Afterwards, return the line item, so it's added as a child line item to the bundle.
Actually calculating the price is then done by Shopware 6 again.

This is how your full `calculateBundleDiscount` method should look like:
```php
private function calculateBundleDiscount(LineItem $bundleLineItem, BundleEntity $bundleData, SalesChannelContext $context): ?LineItem
{
    if ($bundleData->getDiscount() === 0) {
        return null;
    }

    switch ($bundleData->getDiscountType()) {
        case self::DISCOUNT_TYPE_ABSOLUTE:
            $price = new AbsolutePriceDefinition($bundleData->getDiscount() * -1, $context->getContext()->getCurrencyPrecision());
            $label = 'Absolute bundle voucher';
            break;

        case self::DISCOUNT_TYPE_PERCENTAGE:
            $price = new PercentagePriceDefinition($bundleData->getDiscount() * -1, $context->getContext()->getCurrencyPrecision());
            $label = sprintf('Percental bundle voucher (%s%%)', $bundleData->getDiscount());
            break;
    }

    $discount = new LineItem(
        $bundleData->getId() . '-discount',
        self::TYPE . '-discount',
        $bundleLineItem->getQuantity()
    );

    $discount->setPriceDefinition($price)->setLabel($label);

    return $discount;
}
```

### Dealing with manual changes to the cart

There's another scenario you need to consider here. A shop manager is able to edit an order afterwards, for example the discount line item of the bundle.
This would trigger the whole calculation again, and thus, the enrich process. Your code would now collect the bundle's data and reset the
discount again, completely ignoring the shop managers manual change to that.
You don't want that to happen. Also, you don't want your discount to be applied twice. Once a discount line item is available, never
apply another discount item again or change the discount's value.

For this case, you'll have to add another check to the `enrich` process, if the bundle was completely handled already.
Create a new method `isComplete`, which should return a boolean. To figure out, if a bundle line item is complete,
you need to pass the respective bundle line item to this method.
Then you check if the item already has a label and a discount, so it was enriched already.

```php
private function isComplete(LineItem $lineItem): bool
{
    // Has a label
    return $lineItem->getLabel()
        // Has children
        && $lineItem->getChildren() !== null
        // One children's key consists of the bundle key plus the string 'discount'
        && $lineItem->getChildren()->get($lineItem->getKey() . '-discount')
        // This discount children actually knows a price definition
        && $lineItem->getChildren()->get($lineItem->getKey() . '-discount')->getPriceDefinition();
}
```

And call this method in your `enrich` method right after iterating through the bundle line items.

```php
/** @var LineItem $bundleLineItem */
foreach ($bundleLineItems as $bundleLineItem) {
    if ($this->isComplete($bundleLineItem)) {
        continue;
    }
    ...
}
```


**But what if the shop manager increased the bundle's discount in the administration right after a customer put the bundle into the cart?
The discount won't be updated then!**

That's right, the live cart wouldn't be updated. You also need to consider the downside if this was possible:
The customer puts a bundle with 10% discount into the cart, and right in the moment he proceeds to pay this cart, the shop manager decreased the discount.
If you'd update your discount now, the customer would be very upset, because he didn't pay for what he last saw.

Yet, Shopware 6 has this covered as well.
When finish an order, a complete recalculation of the cart is enforced. If there's any difference found to the previous cart, the order will not be created immediately and the cart will be fully recalculated.
Afterwards, a warning will be printed, that his cart has updated and he should re-check if he's still fine with the cart's contents.

**And that's it! The checkout works now as well. You can put a bundle into the cart and have it calculated properly by Shopware 6!**

Your plugin is almost done, just some last polishing is necessary. Head over to the [next step](./100-final-preparation.md) for the last few changes necessary.