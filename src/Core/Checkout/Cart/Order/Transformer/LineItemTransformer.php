<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order\Transformer;

use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\QuantityInformation;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('checkout')]
class LineItemTransformer
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function transformCollection(LineItemCollection $lineItems, ?string $parentId = null): array
    {
        $output = [];
        $position = 1;
        foreach ($lineItems as $lineItem) {
            $output = array_replace($output, self::transform($lineItem, $parentId, $position));
            ++$position;
        }

        return $output;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function transform(LineItem $lineItem, ?string $parentId = null, int $position = 1): array
    {
        $output = [];
        /** @var IdStruct|null $idStruct */
        $idStruct = $lineItem->getExtensionOfType(OrderConverter::ORIGINAL_ID, IdStruct::class);
        if ($idStruct !== null) {
            $id = $idStruct->getId();
        } else {
            $id = Uuid::randomHex();
        }

        $productId = null;
        if ($lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE) {
            $productId = $lineItem->getReferencedId();
        }

        $promotionId = null;
        if ($lineItem->getType() === PromotionProcessor::LINE_ITEM_TYPE) {
            $promotionId = $lineItem->getPayloadValue('promotionId');
        }

        $definition = $lineItem->getPriceDefinition();

        $data = [
            'id' => $id,
            'identifier' => $lineItem->getId(),
            'productId' => $productId,
            'promotionId' => $promotionId,
            'referencedId' => $lineItem->getReferencedId(),
            'quantity' => $lineItem->getQuantity(),
            'type' => $lineItem->getType(),
            'label' => $lineItem->getLabel(),
            'description' => $lineItem->getDescription(),
            'good' => $lineItem->isGood(),
            'removable' => $lineItem->isRemovable(),
            'stackable' => $lineItem->isStackable(),
            'position' => $position,
            'price' => $lineItem->getPrice(),
            'priceDefinition' => $definition,
            'parentId' => $parentId,
            'coverId' => $lineItem->getCover() ? $lineItem->getCover()->getId() : null,
            'payload' => $lineItem->getPayload(),
            'states' => $lineItem->getStates(),
        ];

        $downloads = $lineItem->getExtensionOfType(OrderConverter::ORIGINAL_DOWNLOADS, OrderLineItemDownloadCollection::class);
        if ($downloads instanceof OrderLineItemDownloadCollection) {
            $data['downloads'] = array_values($downloads->map(fn (OrderLineItemDownloadEntity $download): array => ['id' => $download->getId()]));
        }

        $output[$lineItem->getId()] = array_filter($data, fn ($value) => $value !== null);

        if ($lineItem->hasChildren()) {
            $output = [...$output, ...self::transformCollection($lineItem->getChildren(), $id)];
        }

        return $output;
    }

    public static function transformFlatToNested(OrderLineItemCollection $lineItems): LineItemCollection
    {
        $lineItems->sortByPosition();
        $index = [];
        $root = new LineItemCollection();

        foreach ($lineItems as $id => $lineItem) {
            if (!\array_key_exists($id, $index)) {
                $index[$id] = self::createLineItem($lineItem);
            }

            $currentLineItem = $index[$id];

            self::updateLineItem($currentLineItem, $lineItem, $id);

            if ($lineItem->getParentId() === null) {
                $root->add($currentLineItem);

                continue;
            }

            if (!\array_key_exists($lineItem->getParentId(), $index)) {
                $parentItem = $lineItems->get($lineItem->getParentId());
                if ($parentItem === null) {
                    continue;
                }

                // NEXT-21735 - This is covered randomly
                // @codeCoverageIgnoreStart
                $index[$lineItem->getParentId()] = self::createLineItem($parentItem);
                // @codeCoverageIgnoreEnd
            }

            $index[$lineItem->getParentId()]->addChild($currentLineItem);
        }

        return $root;
    }

    private static function updateLineItem(LineItem $lineItem, OrderLineItemEntity $entity, string $id): void
    {
        $lineItem->setId($entity->getIdentifier())
            ->setLabel($entity->getLabel())
            ->setGood($entity->getGood())
            ->setRemovable($entity->getRemovable())
            ->setStackable($entity->getStackable())
            ->setStates($entity->getStates())
            ->addExtension(OrderConverter::ORIGINAL_ID, new IdStruct($id));

        if ($entity->getPayload() !== null) {
            $lineItem->setPayload($entity->getPayload());
        }

        if ($entity->getPrice() !== null) {
            $lineItem->setPrice($entity->getPrice());
        }

        if ($entity->getPriceDefinition() !== null) {
            $lineItem->setPriceDefinition($entity->getPriceDefinition());
        }

        if ($entity->getDownloads() !== null) {
            $lineItem->addExtension(OrderConverter::ORIGINAL_DOWNLOADS, $entity->getDownloads());
        }

        if ($entity->getProduct() !== null) {
            self::setProductData($lineItem, $entity->getProduct());
        }
    }

    private static function createLineItem(OrderLineItemEntity $entity): LineItem
    {
        $item = new LineItem(
            $entity->getIdentifier(),
            $entity->getType() ?? '',
            $entity->getReferencedId(),
            $entity->getQuantity()
        );

        $isNonProduct = \in_array($entity->getType(), [
            LineItem::CREDIT_LINE_ITEM_TYPE,
            LineItem::DISCOUNT_LINE_ITEM,
        ], true);
        $isProduct = \in_array($entity->getType(), [
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            LineItem::CUSTOM_LINE_ITEM_TYPE,
        ], true);
        $isDownloadState = \in_array(State::IS_DOWNLOAD, $entity->getStates(), true);
        if ($isNonProduct || ($isProduct && $isDownloadState)) {
            $item->setShippingCostAware(false);

            return $item;
        }

        return $item;
    }

    private static function setProductData(LineItem $lineItem, ProductEntity $product): void
    {
        if ($product->getCover()) {
            $lineItem->setCover($product->getCover()->getMedia());
        }

        $quantityInformation = new QuantityInformation();

        $quantityInformation->setMinPurchase(
            $product->getMinPurchase() ?? 1
        );

        if ($product->getMaxPurchase() !== null) {
            $quantityInformation->setMaxPurchase(
                $product->getMaxPurchase()
            );
        }

        $quantityInformation->setPurchaseSteps(
            $product->getPurchaseSteps() ?? 1
        );

        $lineItem->setQuantityInformation($quantityInformation);

        if ($lineItem->hasState(State::IS_PHYSICAL)) {
            $deliveryTime = null;
            if ($product->getDeliveryTime() !== null) {
                $deliveryTime = DeliveryTime::createFromEntity($product->getDeliveryTime());
            }

            $lineItem->setDeliveryInformation(
                new DeliveryInformation(
                    $product->getStock(),
                    $product->getWeight(),
                    $product->getShippingFree() === true,
                    $product->getRestockTime(),
                    $deliveryTime,
                    $product->getHeight(),
                    $product->getWidth(),
                    $product->getLength()
                )
            );
        }
    }
}
