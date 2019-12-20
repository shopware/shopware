<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order\Transformer;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Uuid\Uuid;

class LineItemTransformer
{
    private const LINE_ITEM_PLACEHOLDER = 'lineItemPlaceholder';

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

        $data = [
            'id' => $id,
            'identifier' => $lineItem->getId(),
            'productId' => $productId,
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
            'priceDefinition' => $lineItem->getPriceDefinition(),
            'parentId' => $parentId,
            'coverId' => $lineItem->getCover() ? $lineItem->getCover()->getId() : null,
            'payload' => $lineItem->getPayload(),
        ];

        $output[$lineItem->getId()] = array_filter($data, function ($value) {
            return $value !== null;
        });

        if ($lineItem->hasChildren()) {
            $output = array_merge($output, self::transformCollection($lineItem->getChildren(), $id));
        }

        return $output;
    }

    public static function transformFlatToNested(OrderLineItemCollection $lineItems): LineItemCollection
    {
        $lineItems->sortByPosition();
        $index = [];
        $root = new LineItemCollection();

        foreach ($lineItems as $id => $lineItem) {
            if (!array_key_exists($id, $index)) {
                $index[$id] = new LineItem($lineItem->getIdentifier(), self::LINE_ITEM_PLACEHOLDER);
            }

            $currentLineItem = $index[$id];

            self::updateLineItem($currentLineItem, $lineItem, $id);

            if ($lineItem->getParentId() === null) {
                $root->add($currentLineItem);

                continue;
            }

            if (!array_key_exists($lineItem->getParentId(), $index)) {
                $index[$lineItem->getParentId()] = new LineItem($lineItem->getParentId(), self::LINE_ITEM_PLACEHOLDER);
            }

            $index[$lineItem->getParentId()]->addChild($currentLineItem);
        }

        return $root;
    }

    private static function updateLineItem(LineItem $lineItem, OrderLineItemEntity $entity, string $id): void
    {
        $lineItem->setId($entity->getIdentifier())
            ->setType($entity->getType())
            ->setReferencedId($entity->getReferencedId())
            ->setStackable(true)
            ->setQuantity($entity->getQuantity())
            ->setStackable($entity->getStackable())
            ->setLabel($entity->getLabel())
            ->setGood($entity->getGood())
            ->setRemovable($entity->getRemovable())
            ->setStackable($entity->getStackable())
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
    }
}
