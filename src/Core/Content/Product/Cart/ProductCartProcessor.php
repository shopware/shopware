<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\Exception\MissingLineItemPriceException;
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
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductCartProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    public const CUSTOM_PRICE = 'customPrice';

    public const ALLOW_PRODUCT_PRICE_OVERWRITES = 'allowProductPriceOverwrites';

    public const ALLOW_PRODUCT_LABEL_OVERWRITES = 'allowProductLabelOverwrites';

    public const SKIP_PRODUCT_RECALCULATION = 'skipProductRecalculation';

    public const SKIP_PRODUCT_STOCK_VALIDATION = 'skipProductStockValidation';

    public const KEEP_INACTIVE_PRODUCT = 'keepInactiveProduct';

    private ProductGatewayInterface $productGateway;

    private QuantityPriceCalculator $calculator;

    private ProductFeatureBuilder $featureBuilder;

    private AbstractProductPriceCalculator $priceCalculator;

    private EntityCacheKeyGenerator $generator;

    private SalesChannelRepositoryInterface $repository;

    public function __construct(
        ProductGatewayInterface $productGateway,
        QuantityPriceCalculator $calculator,
        ProductFeatureBuilder $featureBuilder,
        AbstractProductPriceCalculator $priceCalculator,
        EntityCacheKeyGenerator $generator,
        SalesChannelRepositoryInterface $repository
    ) {
        $this->productGateway = $productGateway;
        $this->calculator = $calculator;
        $this->featureBuilder = $featureBuilder;
        $this->priceCalculator = $priceCalculator;
        $this->generator = $generator;
        $this->repository = $repository;
    }

    public function collect(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
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

            $hash = $this->generator->getSalesChannelContextHash($context);

            // refresh data timestamp to prevent unnecessary gateway calls
            foreach ($items as $lineItem) {
                if (\in_array($lineItem->getReferencedId(), $products->getIds(), true)) {
                    $lineItem->setDataTimestamp(new \DateTimeImmutable());
                    $lineItem->setDataContextHash($hash);
                }
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
    }

    /**
     * @throws MissingLineItemPriceException
     */
    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        $hash = $this->generator->getSalesChannelContextHash($context);

        $items = $original->getLineItems()->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        foreach ($items as $item) {
            $definition = $item->getPriceDefinition();

            if (!$definition instanceof QuantityPriceDefinition) {
                throw new MissingLineItemPriceException($item->getId());
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

        $name = $product->getTranslation('name');

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

        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                (int) $product->getAvailableStock(),
                (float) $product->getWeight(),
                $product->getShippingFree() === true,
                $product->getRestockTime(),
                $deliveryTime,
                $product->getHeight(),
                $product->getWidth(),
                $product->getLength()
            )
        );

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
            'createdAt' => $product->getCreatedAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'releaseDate' => $product->getReleaseDate() ? $product->getReleaseDate()->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null,
            'isNew' => $product->isNew(),
            'markAsTopseller' => $product->getMarkAsTopseller(),
            'purchasePrices' => $purchasePrices ? json_encode($purchasePrices) : null,
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

        $lineItem->replacePayload($payload);
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

    private function getNotCompleted(CartDataCollection $data, array $lineItems, SalesChannelContext $context): array
    {
        $ids = [];

        $changes = [];

        $hash = $this->generator->getSalesChannelContextHash($context);

        /** @var LineItem $lineItem */
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

            // @internal (flag:FEATURE_NEXT_13250) - The IF must be removed so that $changes is filled
            if (!Feature::isActive('FEATURE_NEXT_13250')) {
                $ids[] = $id;

                continue;
            }

            $changes[$id] = $lineItem->getDataTimestamp()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        }

        // @internal (flag:FEATURE_NEXT_13250) - The IF can be removed completely so that $changes is taken into account.
        if (!Feature::isActive('FEATURE_NEXT_13250')) {
            return $ids;
        }

        if (empty($changes)) {
            return $ids;
        }

        $filter = new OrFilter();
        foreach ($changes as $id => $timestamp) {
            $filter->addQuery(new AndFilter([
                new EqualsFilter('product.id', $id),
                new RangeFilter('updatedAt', [
                    RangeFilter::GTE => $timestamp,
                ]),
            ]));
        }

        $criteria = new Criteria();
        $criteria->setTitle('cart::products::not-completed');
        $criteria->addFilter($filter);

        $changed = $this->repository->searchIds($criteria, $context)->getIds();

        return array_filter(array_unique(array_merge($ids, $changed)));
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
