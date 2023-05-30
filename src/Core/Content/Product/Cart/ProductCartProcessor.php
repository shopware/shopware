<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\QuantityInformation;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePriceDefinition;
use Shopware\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class ProductCartProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    final public const CUSTOM_PRICE = 'customPrice';

    final public const ALLOW_PRODUCT_PRICE_OVERWRITES = 'allowProductPriceOverwrites';

    final public const ALLOW_PRODUCT_LABEL_OVERWRITES = 'allowProductLabelOverwrites';

    final public const SKIP_PRODUCT_RECALCULATION = 'skipProductRecalculation';

    final public const SKIP_PRODUCT_STOCK_VALIDATION = 'skipProductStockValidation';

    final public const KEEP_INACTIVE_PRODUCT = 'keepInactiveProduct';

    /**
     * @internal
     */
    public function __construct(
        private readonly ProductGatewayInterface $productGateway,
        private readonly QuantityPriceCalculator $calculator,
        private readonly ProductFeatureBuilder $featureBuilder,
        private readonly AbstractProductPriceCalculator $priceCalculator,
        private readonly EntityCacheKeyGenerator $generator,
        private readonly Connection $connection
    ) {
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        Profiler::trace('cart::product::collect', function () use ($data, $original, $context, $behavior): void {
            $lineItems = $this->getProducts($original->getLineItems());

            $items = array_column($lineItems, 'item');

            // find products in original cart which requires data from gateway
            $ids = $this->getNotCompleted($data, $items, $context);

            if (!empty($ids)) {
                // fetch missing data over gateway
                $products = $this->productGateway->get($ids, $context);

                // add products to data collection
                foreach ($products as $product) {
                    $data->set($this->getDataKey($product->getId()), $product);
                }

                $hash = $this->generator->getSalesChannelContextHash($context, [RuleAreas::PRODUCT_AREA]);

                // refresh data timestamp to prevent unnecessary gateway calls
                foreach ($items as $lineItem) {
                    if (!\in_array($lineItem->getReferencedId(), $products->getIds(), true)) {
                        $lineItem->setDataTimestamp(null);

                        continue;
                    }
                    $lineItem->setDataTimestamp(new \DateTimeImmutable());
                    $lineItem->setDataContextHash($hash);
                }
            }

            foreach ($lineItems as $match) {
                // enrich all products in original cart
                $this->enrich($context, $match['item'], $data, $behavior);

                // remove "parent" products which should never be displayed in storefront
                $this->validateParents($match['item'], $data, $match['scope']);

                // validate data timestamps that inactive products (or not assigned to sales channel) are removed
                $this->validateTimestamp($match['item'], $original, $data, $behavior, $match['scope']);

                // validate availability of the product stock
                $this->validateStock($match['item'], $original, $match['scope'], $behavior);
            }

            $this->featureBuilder->prepare($items, $data, $context);
        }, 'cart');
    }

    /**
     * @throws CartException
     */
    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        Profiler::trace('cart::product::process', function () use ($data, $original, $toCalculate, $context): void {
            $hash = $this->generator->getSalesChannelContextHash($context);

            $items = $original->getLineItems()->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE);

            foreach ($items as $item) {
                $definition = $item->getPriceDefinition();

                if (!$definition instanceof QuantityPriceDefinition) {
                    throw CartException::missingLineItemPrice($item->getId());
                }
                $definition->setQuantity($item->getQuantity());

                $item->setPrice($this->calculator->calculate($definition, $context));
                $item->setDataContextHash($hash);
            }

            $this->featureBuilder->add($items, $data, $context);

            // handle all products which stored in root level
            $items = $original->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);

            foreach ($items as $item) {
                $toCalculate->add($item);
            }
        }, 'cart');
    }

    /**
     * @return list<array{'item': LineItem, 'scope': LineItemCollection}>
     */
    private function getProducts(LineItemCollection $items): array
    {
        $matches = [];
        foreach ($items as $item) {
            if ($item->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE) {
                $matches[] = ['item' => $item, 'scope' => $items];
            }

            $nested = $this->getProducts($item->getChildren());

            foreach ($nested as $match) {
                $matches[] = $match;
            }
        }

        return $matches;
    }

    private function validateTimestamp(LineItem $item, Cart $cart, CartDataCollection $data, CartBehavior $behavior, LineItemCollection $items): void
    {
        $product = $data->get(
            $this->getDataKey((string) $item->getReferencedId())
        );

        // product data was never detected and the product is not inside the data collection
        if ($product !== null || $item->getDataTimestamp() !== null) {
            return;
        }

        if ($behavior->hasPermission(self::KEEP_INACTIVE_PRODUCT)) {
            return;
        }

        $cart->addErrors(new ProductNotFoundError($item->getLabel() ?: $item->getId()));

        $items->remove($item->getId());
    }

    private function validateParents(LineItem $item, CartDataCollection $data, LineItemCollection $items): void
    {
        $product = $data->get(
            $this->getDataKey((string) $item->getReferencedId())
        );

        // no data for enrich exists
        if (!$product instanceof SalesChannelProductEntity) {
            return;
        }

        // container products can not be bought
        if ($product->getChildCount() <= 0) {
            return;
        }

        $items->remove($item->getId());
    }

    private function validateStock(LineItem $item, Cart $cart, LineItemCollection $scope, CartBehavior $behavior): void
    {
        if ($behavior->hasPermission(self::SKIP_PRODUCT_STOCK_VALIDATION)) {
            return;
        }

        $minPurchase = 1;
        $steps = 1;
        $available = $item->getQuantity();

        if ($item->getQuantityInformation() !== null) {
            $minPurchase = $item->getQuantityInformation()->getMinPurchase();
            $available = $item->getQuantityInformation()->getMaxPurchase() ?? 0;
            $steps = $item->getQuantityInformation()->getPurchaseSteps() ?? 1;
        }

        if ($available < $minPurchase) {
            $scope->remove($item->getId());

            $cart->addErrors(
                new ProductOutOfStockError((string) $item->getReferencedId(), (string) $item->getLabel())
            );

            return;
        }

        if ($available < $item->getQuantity()) {
            $maxAvailable = $this->fixQuantity($minPurchase, $available, $steps);

            $item->setQuantity($maxAvailable);

            $cart->addErrors(
                new ProductStockReachedError((string) $item->getReferencedId(), (string) $item->getLabel(), $maxAvailable)
            );

            return;
        }

        if ($item->getQuantity() < $minPurchase) {
            $item->setQuantity($minPurchase);

            $cart->addErrors(
                new MinOrderQuantityError((string) $item->getReferencedId(), (string) $item->getLabel(), $minPurchase)
            );

            return;
        }

        $fixedQuantity = $this->fixQuantity($minPurchase, $item->getQuantity(), $steps);
        if ($item->getQuantity() !== $fixedQuantity) {
            $item->setQuantity($fixedQuantity);

            $cart->addErrors(
                new PurchaseStepsError((string) $item->getReferencedId(), (string) $item->getLabel(), $fixedQuantity)
            );
        }
    }

    private function enrich(SalesChannelContext $context, LineItem $lineItem, CartDataCollection $data, CartBehavior $behavior): void
    {
        $id = $lineItem->getReferencedId();

        $product = $data->get(
            $this->getDataKey((string) $id)
        );

        // no data for enrich exists
        if (!$product instanceof SalesChannelProductEntity) {
            return;
        }

        $label = trim($lineItem->getLabel() ?? '');

        // set the label if its empty or the context does not have the permission to overwrite it
        if ($label === '' || !$behavior->hasPermission(self::ALLOW_PRODUCT_LABEL_OVERWRITES)) {
            $lineItem->setLabel($product->getTranslation('name'));
        }

        if ($product->getCover()) {
            $lineItem->setCover($product->getCover()->getMedia());
        }

        $deliveryTime = null;
        if ($product->getDeliveryTime() !== null) {
            $deliveryTime = DeliveryTime::createFromEntity($product->getDeliveryTime());
        }

        $weight = $product->getWeight();

        $lineItem->setStates($product->getStates());

        if ($lineItem->hasState(State::IS_PHYSICAL)) {
            $lineItem->setDeliveryInformation(
                new DeliveryInformation(
                    (int) $product->getAvailableStock(),
                    $weight,
                    $product->getShippingFree() === true,
                    $product->getRestockTime(),
                    $deliveryTime,
                    $product->getHeight(),
                    $product->getWidth(),
                    $product->getLength()
                )
            );
        }

        //Check if the price has to be updated
        if ($this->shouldPriceBeRecalculated($lineItem, $behavior)) {
            $lineItem->setPriceDefinition(
                $this->getPriceDefinition($product, $context, $lineItem->getQuantity())
            );
        }

        $quantityInformation = new QuantityInformation();

        $quantityInformation->setMinPurchase(
            $product->getMinPurchase() ?? 1
        );

        $quantityInformation->setMaxPurchase(
            $product->getCalculatedMaxPurchase()
        );

        $quantityInformation->setPurchaseSteps(
            $product->getPurchaseSteps() ?? 1
        );

        $lineItem->setQuantityInformation($quantityInformation);

        $purchasePrices = null;
        $purchasePricesCollection = $product->getPurchasePrices();
        if ($purchasePricesCollection !== null) {
            $purchasePrices = $purchasePricesCollection->getCurrencyPrice(Defaults::CURRENCY);
        }

        $payload = [
            'isCloseout' => $product->getIsCloseout(),
            'customFields' => $product->getCustomFields(),
            'createdAt' => $product->getCreatedAt() ? $product->getCreatedAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null,
            'releaseDate' => $product->getReleaseDate() ? $product->getReleaseDate()->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null,
            'isNew' => $product->isNew(),
            'markAsTopseller' => $product->getMarkAsTopseller(),
            'purchasePrices' => $purchasePrices ? json_encode($purchasePrices, \JSON_THROW_ON_ERROR) : null,
            'productNumber' => $product->getProductNumber(),
            'manufacturerId' => $product->getManufacturerId(),
            'taxId' => $product->getTaxId(),
            'tagIds' => $product->getTagIds(),
            'categoryIds' => $product->getCategoryTree(),
            'propertyIds' => $product->getPropertyIds(),
            'optionIds' => $product->getOptionIds(),
            'options' => $product->getVariation(),
            'streamIds' => $product->getStreamIds(),
            'parentId' => $product->getParentId(),
            'stock' => $product->getStock(),
        ];

        $lineItem->replacePayload($payload, ['purchasePrices' => true]);
    }

    private function getPriceDefinition(SalesChannelProductEntity $product, SalesChannelContext $context, int $quantity): QuantityPriceDefinition
    {
        $this->priceCalculator->calculate([$product], $context);

        if ($product->getCalculatedPrices()->count() === 0) {
            return $this->buildPriceDefinition($product->getCalculatedPrice(), $quantity);
        }

        // keep loop reference to $price variable to get last quantity price in case of "null"
        $price = $product->getCalculatedPrice();
        foreach ($product->getCalculatedPrices() as $price) {
            if ($quantity <= $price->getQuantity()) {
                break;
            }
        }

        return $this->buildPriceDefinition($price, $quantity);
    }

    private function buildPriceDefinition(CalculatedPrice $price, int $quantity): QuantityPriceDefinition
    {
        $definition = new QuantityPriceDefinition($price->getUnitPrice(), $price->getTaxRules(), $quantity);
        if ($price->getListPrice() !== null) {
            $definition->setListPrice($price->getListPrice()->getPrice());
        }

        if ($price->getReferencePrice() !== null) {
            $definition->setReferencePriceDefinition(
                new ReferencePriceDefinition(
                    $price->getReferencePrice()->getPurchaseUnit(),
                    $price->getReferencePrice()->getReferenceUnit(),
                    $price->getReferencePrice()->getUnitName()
                )
            );
        }

        return $definition;
    }

    /**
     * @param LineItem[] $lineItems
     *
     * @return mixed[]
     */
    private function getNotCompleted(CartDataCollection $data, array $lineItems, SalesChannelContext $context): array
    {
        $ids = [];

        $changes = [];

        $hash = $this->generator->getSalesChannelContextHash($context);

        foreach ($lineItems as $lineItem) {
            $id = $lineItem->getReferencedId();

            $key = $this->getDataKey((string) $id);

            // data already fetched?
            if ($data->has($key)) {
                continue;
            }

            // user change line item quantity or price?
            if ($lineItem->isModified()) {
                $ids[] = $id;

                continue;
            }

            if ($lineItem->getDataTimestamp() === null) {
                $ids[] = $id;

                continue;
            }

            if ($lineItem->getDataContextHash() !== $hash) {
                $ids[] = $id;

                continue;
            }

            // check if some data is missing (label, price, cover)
            if (!$this->isComplete($lineItem)) {
                $ids[] = $id;

                continue;
            }

            $changes[$id] = $lineItem->getDataTimestamp()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        }

        if (empty($changes)) {
            return $ids;
        }

        $updates = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)) as id, updated_at FROM product WHERE id IN (:ids) AND version_id = :liveVersionId',
            [
                'ids' => Uuid::fromHexToBytesList(array_keys($changes)),
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            [
                'ids' => ArrayParameterType::STRING,
            ]
        );

        foreach ($changes as $id => $timestamp) {
            // Product has been deleted, as we cannot find it
            if (!isset($updates[$id])) {
                $ids[] = $id;

                continue;
            }

            // Product has been updated, but the timestamp is older than the one we have
            if ($updates[$id] !== $changes[$id]) {
                $ids[] = $id;
            }
        }

        return array_filter(array_unique($ids));
    }

    private function isComplete(LineItem $lineItem): bool
    {
        return $lineItem->getPriceDefinition() !== null
            && $lineItem->getLabel() !== null
            && $lineItem->getDeliveryInformation() !== null
            && $lineItem->getQuantityInformation() !== null;
    }

    private function shouldPriceBeRecalculated(LineItem $lineItem, CartBehavior $behavior): bool
    {
        if ($lineItem->getPriceDefinition() !== null
            && $lineItem->hasExtension(self::CUSTOM_PRICE)
            && $behavior->hasPermission(self::ALLOW_PRODUCT_PRICE_OVERWRITES)) {
            return false;
        }

        if ($lineItem->getPriceDefinition() !== null
            && $behavior->hasPermission(self::SKIP_PRODUCT_RECALCULATION)) {
            return false;
        }

        if ($lineItem->getPriceDefinition() !== null && $lineItem->isModifiedByApp()) {
            return false;
        }

        return true;
    }

    private function fixQuantity(int $min, int $current, int $steps): int
    {
        return (int) (floor(($current - $min) / $steps) * $steps + $min);
    }

    private function getDataKey(string $id): string
    {
        return 'product-' . $id;
    }
}
