<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\Facade\ItemFacade;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

trait ItemsAddTrait
{
    use ItemsGetTrait;

    /**
     * `add()` adds a line-item to this collection.
     *
     * @param ItemFacade $item The line-item that should be added.
     *
     * @return ItemFacade Returns the added line-item.
     */
    public function add(ItemFacade $item): ItemFacade
    {
        $this->items->add($item->getItem());

        /** @var ItemFacade $item */
        $item = $this->get($item->getId());

        return $item;
    }

    private function getItems(): LineItemCollection
    {
        return $this->items;
    }
}
