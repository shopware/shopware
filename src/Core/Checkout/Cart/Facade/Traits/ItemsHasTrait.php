<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\Facade\ItemFacade;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

/**
 * @internal The trait is not intended for re-usability in other domains
 */
trait ItemsHasTrait
{
    protected LineItemCollection $items;

    /**
     * @param string|ItemFacade $id
     */
    public function has($id): bool
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
            if ($item->getId() === $id->getId()) {
                return true;
            }

            // same type and same reference id
            if ($item->getReferencedId() === $id->getReferencedId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @internal
     */
    protected function getItems(): LineItemCollection
    {
        return $this->items;
    }
}
