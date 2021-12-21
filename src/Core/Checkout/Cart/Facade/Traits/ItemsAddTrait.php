<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\Facade\ItemFacade;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

trait ItemsAddTrait
{
    use ItemsGetTrait;

    public function add(ItemFacade $item): ?ItemFacade
    {
        $this->items->add($item->getItem());

        return $this->get($item->getId());
    }

    private function getItems(): LineItemCollection
    {
        return $this->items;
    }
}
