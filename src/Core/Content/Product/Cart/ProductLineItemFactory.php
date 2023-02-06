<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - will be removed, use \Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory instead
 */
#[Package('inventory')]
class ProductLineItemFactory
{
    /**
     * @param array<string, mixed>[] $products
     */
    public function createList(array $products): LineItemCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            'Will be removed, use \Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory instead',
        );

        $lineItems = new LineItemCollection();

        foreach ($products as $id => $config) {
            $lineItems->add($this->create($id, $config));
        }

        return $lineItems;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function create(string $id, array $config = []): LineItem
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            'Will be removed, use \Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory instead',
        );

        $quantity = isset($config['quantity']) ? (int) $config['quantity'] : 1;

        return (new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, $id, $quantity))
            ->setRemovable(true)
            ->setStackable(true);
    }
}
