<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Util\Transformer;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Framework\Struct\Uuid;

class LineItemTransformer
{
    public function transformCollection(LineItemCollection $lineItems, ?string $parentId = null): array
    {
        $output = [];
        foreach ($lineItems as $lineItem) {
            $output = array_replace($output, self::transform($lineItem, $parentId));
        }

        return $output;
    }

    public function transform(LineItem $lineItem, ?string $parentId = null): array
    {
        $output = [];
        /** @var IdStruct|null $idStruct */
        $idStruct = $lineItem->getExtensionOfType(OrderConverter::ORIGINAL_ID, IdStruct::class);
        if ($idStruct !== null) {
            $id = $idStruct->getId();
        } else {
            $id = Uuid::uuid4()->getHex();
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
