<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\Facade\ItemFacade;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

trait ItemsRemoveTrait
{
    private LineItemCollection $items;

    /**
     * @param string|ItemFacade $id
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
