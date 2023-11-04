<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\Facade\ItemFacade;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
trait ItemsHasTrait
{
    private LineItemCollection $items;

    /**
     * `has()` checks if a line-item with the given id exists in this collection.
     *
     * @param string|ItemFacade $id The id or a line-item that should be checked if it already exists in the collection.
     *
     * @return bool Returns true if the given line-item or a line-item with the given id already exists in the collection, false otherwise.
     */
    public function has(string|ItemFacade $id): bool
    {
        if (\is_string($id)) {
            return $this->getItems()->has($id);
        }
        if (!$id instanceof ItemFacade) {
            return false;
        }

        if ($this->getItems()->has($id->getId())) {
            return true;
        }

        foreach ($this->getItems() as $item) {
            if ($item->getType() !== $id->getType()) {
                continue;
            }

            // same type and same reference id
            if ($item->getReferencedId() === $id->getReferencedId()) {
                return true;
            }
        }

        return false;
    }

    private function getItems(): LineItemCollection
    {
        return $this->items;
    }
}
