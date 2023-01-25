<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\Facade\ItemFacade;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
trait ItemsRemoveTrait
{
    private LineItemCollection $items;

    /**
     * `remove()` removes the given line-item or the line-item with the given id from this collection.
     *
     * @param string|ItemFacade $id The id of the line-item or the line-item that should be removed.
     *
     * @example remove-product-cases/remove-product-cases.twig 2 3 Add and then remove a product line-item from the cart.
     */
    public function remove(string|ItemFacade $id): void
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
