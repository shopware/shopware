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
use Shopware\Core\Checkout\Cart\LineItem\QuantityInformation;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePriceDefinition;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceCalculator;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductCartProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    public const CUSTOM_PRICE = 'customPrice';

    public const ALLOW_PRODUCT_PRICE_OVERWRITES = 'allowProductPriceOverwrites';

    public const ALLOW_PRODUCT_LABEL_OVERWRITES = 'allowProductLabelOverwrites';

    public const SKIP_PRODUCT_RECALCULATION = 'skipProductRecalculation';

    public const SKIP_PRODUCT_STOCK_VALIDATION = 'skipProductStockValidation';

    private ProductGatewayInterface $productGateway;

    private QuantityPriceCalculator $calculator;

    private ProductFeatureBuilder $featureBuilder;

    private ProductPriceCalculator $priceCalculator;

    public function __construct(
        ProductGatewayInterface $productGateway,
        QuantityPriceCalculator $calculator,
        ProductFeatureBuilder $featureBuilder,
        ProductPriceCalculator $priceCalculator
    ) {
        $this->productGateway = $productGateway;
        $this->calculator = $calculator;
        $this->featureBuilder = $featureBuilder;
        $this->priceCalculator = $priceCalculator;
    }

    public function collect(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        $lineItems = $original
            ->getLineItems()
            ->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        // find products in original cart which requires data from gateway
        $ids = $this->getNotCompleted($data, $lineItems);

        if (!empty($ids)) {
            // fetch missing data over gateway
            $products = $this->productGateway->get($ids, $context);

            // add products to data collection
            foreach ($products as $product) {
                $data->set('product-' . $product->getId(), $product);
            }
        }

        foreach ($lineItems as $lineItem) {
            // enrich all products in original cart
            $this->enrich($original, $context, $lineItem, $data, $behavior);
        }

        $this->featureBuilder->prepare($lineItems, $data, $context);
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
        // handle all products which stored in root level
        $lineItems = $original
            ->getLineItems()
            ->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        foreach ($lineItems as $lineItem) {
            $definition = $lineItem->getPriceDefinition();

            if (!$definition instanceof QuantityPriceDefinition) {
                throw new MissingLineItemPriceException($lineItem->getId());
            }

            $definition->setQuantity($lineItem->getQuantity());

            if ($behavior->hasPermission(self::SKIP_PRODUCT_STOCK_VALIDATION)) {
                $lineItem->setPrice($this->calculator->calculate($definition, $context));
                $toCalculate->add($lineItem);

                continue;
            }

            /** @var SalesChannelProductEntity $product */
            $product = $data->get('product-' . $lineItem->getReferencedId());

            // container products can not be bought
            if ($product->getChildCount() > 0) {
                $original->remove($lineItem->getId());

                continue;
            }

            $minPurchase = $product->getMinPurchase() ?? 1;
            if ($lineItem->getQuantity() < $minPurchase) {
                $lineItem->setQuantity($minPurchase);
                $definition->setQuantity($minPurchase);
            }

            $available = $product->getCalculatedMaxPurchase();

            if ($available <= 0 || $available < $minPurchase) {
                $original->remove($lineItem->getId());

                $toCalculate->addErrors(
                    new ProductOutOfStockError($product->getId(), (string) $product->getTranslation('name'))
                );

                continue;
            }

            if ($available < $lineItem->getQuantity()) {
                $lineItem->setQuantity($available);

                $definition->setQuantity($available);

                $toCalculate->addErrors(
                    new ProductStockReachedError($product->getId(), (string) $product->getTranslation('name'), $available)
                );
            }

            $fixedQuantity = $this->fixQuantity($minPurchase, $lineItem->getQuantity(), $product->getPurchaseSteps() ?? 1);
            if ($lineItem->getQuantity() !== $fixedQuantity) {
                $lineItem->setQuantity($fixedQuantity);
                $definition->setQuantity($fixedQuantity);

                $toCalculate->addErrors(
                    new PurchaseStepsError($product->getId(), (string) $product->getTranslation('name'), $fixedQuantity)
                );
            }

            $lineItem->setPrice($this->calculator->calculate($definition, $context));

            $toCalculate->add($lineItem);
        }

        $this->featureBuilder->add($lineItems, $data, $context);
    }

    private function enrich(
        Cart $cart,
        SalesChannelContext $context,
        LineItem $lineItem,
        CartDataCollection $data,
        CartBehavior $behavior
    ): void {
        $id = $lineItem->getReferencedId();

        $key = 'product-' . $id;

        $product = $data->get($key);

        if (!$product instanceof SalesChannelProductEntity) {
            $cart->addErrors(new ProductNotFoundError($lineItem->getLabel() ?: $lineItem->getId()));
            $cart->getLineItems()->remove($lineItem->getId());

            return;
        }

        // already enriched and not modified? Skip
        if ($this->isComplete($lineItem) && !$lineItem->isModified()) {
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

        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                (int) $product->getAvailableStock(),
                (float) $product->getWeight(),
                $product->getShippingFree(),
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
            'options' => $this->getOptions($product),
        ];

        $payload['options'] = $product->getVariation();

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

    private function getNotCompleted(CartDataCollection $data, array $lineItems): array
    {
        $ids = [];

        /** @var LineItem $lineItem */
        foreach ($lineItems as $lineItem) {
            $id = $lineItem->getReferencedId();

            $key = 'product-' . $id;

            // data already fetched?
            if ($data->has($key)) {
                continue;
            }

            // user change line item quantity or price?
            if ($lineItem->isModified()) {
                $ids[] = $id;

                continue;
            }

            // already enriched?
            if ($this->isComplete($lineItem)) {
                continue;
            }

            $ids[] = $id;
        }

        return $ids;
    }

    private function isComplete(LineItem $lineItem): bool
    {
        return $lineItem->getPriceDefinition() !== null
            && $lineItem->getLabel() !== null
            && $lineItem->getCover() !== null
            && $lineItem->getDescription() !== null
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

    private function getOptions(SalesChannelProductEntity $product): array
    {
        $options = [];

        if (!$product->getOptions()) {
            return $options;
        }

        foreach ($product->getOptions() as $option) {
            if (!$option->getGroup()) {
                continue;
            }

            $options[] = [
                'group' => $option->getGroup()->getTranslation('name'),
                'option' => $option->getTranslation('name'),
            ];
        }

        return $options;
    }

    private function fixQuantity(int $min, int $current, int $steps): int
    {
        return (int) (floor(($current - $min) / $steps) * $steps + $min);
    }
}
