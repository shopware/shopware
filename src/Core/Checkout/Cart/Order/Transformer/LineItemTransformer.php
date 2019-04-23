<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order\Transformer;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Framework\Uuid\Uuid;

class LineItemTransformer
{
    public static function transformCollection(LineItemCollection $lineItems, ?string $parentId = null): array
    {
        $output = [];
        foreach ($lineItems as $lineItem) {
            $output = array_replace($output, self::transform($lineItem, $parentId));
        }

        return $output;
    }

    public static function transform(LineItem $lineItem, ?string $parentId = null): array
    {
        $output = [];
        /** @var IdStruct|null $idStruct */
        $idStruct = $lineItem->getExtensionOfType(OrderConverter::ORIGINAL_ID, IdStruct::class);
        if ($idStruct !== null) {
            $id = $idStruct->getId();
        } else {
            $id = Uuid::randomHex();
        }

        $data = [
            'id' => $id,
            'identifier' => $lineItem->getKey(),
            'quantity' => $lineItem->getQuantity(),
            'type' => $lineItem->getType(),
            'label' => $lineItem->getLabel(),
            'description' => $lineItem->getDescription(),
            'priority' => $lineItem->getPriority(),
            'good' => $lineItem->isGood(),
            'removable' => $lineItem->isRemovable(),
            'stackable' => $lineItem->isStackable(),
            'price' => $lineItem->getPrice(),
            'priceDefinition' => $lineItem->getPriceDefinition(),
            'parentId' => $parentId,
            'coverId' => $lineItem->getCover() ? $lineItem->getCover()->getId() : null,
            'payload' => $lineItem->getPayload(),
        ];

        $output[$lineItem->getKey()] = array_filter($data, function ($value) {
            return $value !== null;
        });

        if ($lineItem->hasChildren()) {
            $output = array_merge($output, self::transformCollection($lineItem->getChildren(), $id));
        }

        return $output;
    }
}
