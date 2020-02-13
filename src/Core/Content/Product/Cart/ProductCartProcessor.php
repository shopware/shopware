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
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceDefinitionBuilderInterface;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductCartProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    public const CUSTOM_PRICE = 'customPrice';

    public const ALLOW_PRODUCT_PRICE_OVERWRITES = 'allowProductPriceOverwrites';

    public const SKIP_PRODUCT_RECALCULATION = 'skipProductRecalculation';

    /**
     * @var ProductGatewayInterface
     */
    private $productGateway;

    /**
     * @var ProductPriceDefinitionBuilderInterface
     */
    private $priceDefinitionBuilder;

    /**
     * @var QuantityPriceCalculator
     */
    private $calculator;

    public function __construct(
        ProductGatewayInterface $productGateway,
        QuantityPriceCalculator $calculator,
        ProductPriceDefinitionBuilderInterface $priceDefinitionBuilder
    ) {
        $this->productGateway = $productGateway;
        $this->priceDefinitionBuilder = $priceDefinitionBuilder;
        $this->calculator = $calculator;
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
            $this->enrich($original, $lineItem, $data, $context, $behavior);
        }
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

            if ($behavior->hasPermission(self::ALLOW_PRODUCT_PRICE_OVERWRITES)) {
                $definition->setQuantity($lineItem->getQuantity());
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

            $available = $product->getCalculatedMaxPurchase() ?? $lineItem->getQuantity();

            if ($available <= 0 || $available < $product->getMinPurchase()) {
                $original->remove($lineItem->getId());

                $original->addErrors(
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

            $lineItem->setPrice($this->calculator->calculate($definition, $context));
            $toCalculate->add($lineItem);
        }
    }

    private function enrich(
        Cart $cart,
        LineItem $lineItem,
        CartDataCollection $data,
        SalesChannelContext $context,
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

        $lineItem->setLabel($product->getTranslation('name'));

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
            //In Case keep original Price of Product
            $prices = $this->priceDefinitionBuilder->build($product, $context, $lineItem->getQuantity());

            $lineItem->setPriceDefinition($prices->getQuantityPrice());
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

        $lineItem->replacePayload([
            'isCloseout' => $product->getIsCloseout(),
            'customFields' => $product->getCustomFields(),
            'createdAt' => $product->getCreatedAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'releaseDate' => $product->getReleaseDate() ? $product->getReleaseDate()->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null,
            'isNew' => $product->isNew(),
            'markAsTopseller' => $product->getMarkAsTopseller(),
            'purchasePrice' => $product->getPurchasePrice(),
            'productNumber' => $product->getProductNumber(),
            'manufacturerId' => $product->getManufacturerId(),
            'taxId' => $product->getTaxId(),
            'tagIds' => $product->getTagIds(),
            'categoryIds' => $product->getCategoryTree(),
            'propertyIds' => $product->getPropertyIds(),
            'optionIds' => $product->getOptionIds(),
            'options' => $this->getOptions($product),
        ]);
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
}
