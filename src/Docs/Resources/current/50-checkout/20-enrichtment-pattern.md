[titleEn]: <>(Enrichment pattern)
[titleDe]: <>(Enrichment pattern)
[wikiUrl]: <>(../checkout/enrichment?category=shopware-platform-en/checkout)

Shopware has an enrichtment process which allows adding additional information to line items like
label, description, price definition, taxes, images, children and much more. The enrichtment process
executes the collector chain. You can write your own collector to add information to your custom line 
items. This approach enables Shopware to recalculate the cart at any time.

It's important that you only use the LineItem class and the provided entry points to ensure 
that the calculation will also work even if your plugin is no longer installed or activated. 
Please do not change the calculation process itself to implement your changes!

## Register a collector
Cart collectors are registered via the Symfony DI container tag named `shopware.cart.collector`.
To define the order of the calculation queue, the tag supports the `priority` attribute:
```xml
<service id="Shopware\Core\Content\Product\Cart\ProductCollector">
    <tag name="shopware.cart.collector" priority="1000" />
</service>
```
A high `priority` implies an early invocation of the collector. The `priority` defaults to `0`.
Currently the following processors are registered.

| priority | service id | task |
| -------- | ---------- | ---- |
| 0 | Shopware\Core\Content\Product\Cart\ProductCollector |  handle products added to the cart |
| 0 | Shopware\Core\Checkout\DiscountSurcharge\Cart\DiscountSurchargeCollector | handle vouchers, discounts and surcharges added to the cart |

## How a collector (should) work
A collector should only add information to line items. The `Shopware\Core\Checkout\Cart\CollectorInterface`
has three methods:

- prepare: Identify all line items which you need later on. Since all prepare 
methods will run before the collect method, we can avoid fetching data multiple times 
and can add fetch definitions for other collectors. Please avoid slow operations 
like database or filesystem access and do not modify the cart during the prepare process.

- collect: Fetch all needed information (e.g. from the database). Please do not 
modify the cart during the collect process.

- enrich: Add the collected information to the line item. 
Please be aware that this function might be called multiple times.

The methods above will be called in the following order:

1. CollectorX: prepare (priority 100)
2. CollectorY: prepare (priority 0)
3. CollectorX: collect (priority 100)
4. CollectorY: collect (priority 0)
5. CollectorX: enrich (priority 100)
6. CollectorY: enrich (priority 0)

A simple collector could look like this:
```php
<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CollectorInterface;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\Cart\Struct\ProductFetchDefinition;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\ProductPriceDefinitionBuilderInterface;
use Shopware\Core\Framework\Struct\StructCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductCollector implements CollectorInterface
{
    public const DATA_KEY = 'products';
    public const LINE_ITEM_TYPE = 'product';

    /**
     * @var ProductGatewayInterface
     */
    private $productGateway;
    
    /**
      * @var ProductPriceDefinitionBuilderInterface
      */
    private $priceDefinitionBuilder;

    public function __construct(ProductGatewayInterface $productGateway, ProductPriceDefinitionBuilderInterface $priceDefinitionBuilder)
    {
        $this->productGateway = $productGateway;
        $this->priceDefinitionBuilder = $priceDefinitionBuilder;
    }

    public function prepare(StructCollection $definitions, Cart $cart, SalesChannelContext $context): void
    {
        // get all line items with type 'product' (including children)
        $lineItems = array_filter(
            $cart->getLineItems()->getFlat(),
            function (LineItem $lineItem) {
                return $lineItem->getType() === self::LINE_ITEM_TYPE;
            }
        );

        // skip if no line items found
        if (count($lineItems) <= 0) {
            return;
        }

        $ids = [];
        /** @var LineItem $lineItem */
        foreach ($lineItems as $lineItem) {
            // skip if line item already has all required information
            if ($this->isSatisfied($lineItem)) {
                continue;
            }

            $payload = $lineItem->getPayload();

            $ids[] = $payload['id'];
        }

        // create fetch definition for line item and add it to the definition collection
        $definitions->add(new ProductFetchDefinition($ids));
    }

    public function collect(StructCollection $fetchDefinitions, StructCollection $data, Cart $cart, SalesChannelContext $context): void
    {
        $productDefinitions = $fetchDefinitions->filterInstance(ProductFetchDefinition::class);

        // get all fetch definitons (be aware that other plugins might have added additional definitions)
        if ($productDefinitions->count() <= 0) {
            return;
        }

        $ids = [];
        /** @var StructCollection $productDefinitions */
        foreach ($productDefinitions as $definition) {
            /** @var ProductFetchDefinition $definition */
            foreach ($definition->getIds() as $id) {
                // merge all ids
                $ids[] = $id;
            }
        }

        // get information
        $products = $this->productGateway->get($ids, $context);

        // add information to the struct
        $data->add($products, self::DATA_KEY);
    }

    public function enrich(StructCollection $data, Cart $cart, SalesChannelContext $context): void
    {
        // skip if not data is given
        if (!$data->has(self::DATA_KEY)) {
            return;
        }

        /** @var ProductCollection $products */
        $products = $data->get(self::DATA_KEY);

        // get all line items of type 'product' (including children) 
        $flat = array_filter(
            $cart->getLineItems()->getFlat(),
            function (LineItem $lineItem) {
                return $lineItem->getType() === self::LINE_ITEM_TYPE;
            }
        );

        // skip if no line items could be found
        if (\count($flat) <= 0) {
            return;
        }

        /** @var LineItem $lineItem */
        foreach ($flat as $lineItem) {
            // skip if the line item is already complete
            if ($this->isSatisfied($lineItem)) {
                continue;
            }

            $id = $lineItem->getPayload()['id'];

            $product = $products->get($id);

            if (!$product) {
                throw new ProductNotFoundException($id);
            }

            if (!$lineItem->getLabel()) {
                $lineItem->setLabel($product->getName());
            }

            if (!$lineItem->getDescription()) {
                $lineItem->setDescription($product->getDescription());
            }

            if (!$lineItem->getCover() && $product->getCover()) {
                $lineItem->setCover($product->getCover()->getMedia());
            }

            if (!$lineItem->getDeliveryInformation()) {
                $lineItem->setDeliveryInformation(
                    new DeliveryInformation(
                        (int) $product->getStock(),
                        (float) $product->getWeight(),
                        $product->getDeliveryDate(),
                        $product->getRestockDeliveryDate()
                    )
                );
            }

            if (!$lineItem->getPriceDefinition() && !$lineItem->getPrice()) {
                $lineItem->setPriceDefinition(
                    $this->priceDefinitionBuilder->getPriceDefinitionForQuantity(
                        $product,
                        $context,
                        $lineItem->getQuantity()
                    )
                );
            }
        }
    }

    private function isSatisfied(LineItem $lineItem): bool
    {
        return
            ($lineItem->getPriceDefinition() || $lineItem->getPrice())
            &&
            $lineItem->getLabel() !== null
            &&
            $lineItem->getCover() !== null
            &&
            $lineItem->getDescription() !== null
            &&
            $lineItem->getDeliveryInformation() !== null
        ;
    }
}
```



