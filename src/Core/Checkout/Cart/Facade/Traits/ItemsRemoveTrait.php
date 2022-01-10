<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\Facade\ItemFacade;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

trait ItemsRemoveTrait
{
    private LineItemCollection $items;

    /**
     * `remove()` removes the given line-item or the line-item with the given id from this collection.
     *
     * @param string|ItemFacade $id The id of the line-item or the line-item that should be removed.
     */
    public function remove($id): void
    {
        if ($id instanceof ItemFacade) {
            $id = $id->getId();
        }

        $this->getItems()->remove($id);
    }

    private function getItems(): LineItemCollection
    {
        return $this->items;
    }
}
