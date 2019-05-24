<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

class ProductLineItemFactory
{
    public function createList(array $products): LineItemCollection
    {
        $lineItems = new LineItemCollection();

        foreach ($products as $id => $config) {
            $lineItems->add($this->create($id, $config));
        }

        return $lineItems;
    }

    public function create(string $id, array $config = []): LineItem
    {
        $quantity = isset($config['quantity']) ? (int) $config['quantity'] : 1;

        return (new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, $id, $quantity))
            ->setRemovable(true)
            ->setStackable(true);
    }
}
