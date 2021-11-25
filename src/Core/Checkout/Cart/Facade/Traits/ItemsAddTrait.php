<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\Facade\ItemFacade;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

/**
 * @internal The trait is not intended for re-usability in other domains
 */
trait ItemsAddTrait
{
    use ItemsGetTrait;

    /**
     * @public-api used for app scripting
     */
    public function add(ItemFacade $item): ?ItemFacade
    {
        $this->items->add($item->getItem());

        return $this->get($item->getId());
    }

    /**
     * @internal
     */
    protected function getItems(): LineItemCollection
    {
        return $this->items;
    }
}
