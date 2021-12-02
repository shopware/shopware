<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\Facade\ItemFacade;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

/**
 * @internal The trait is not intended for re-usability in other domains
 */
trait ItemsRemoveTrait
{
    protected LineItemCollection $items;

    /**
     * @param string|ItemFacade $id
     * @public-api used for app scripting
     */
    public function remove($id): void
    {
        if ($id instanceof ItemFacade) {
            $id = $id->getId();
        }

        $this->getItems()->remove($id);
    }

    /**
     * @internal
     */
    protected function getItems(): LineItemCollection
    {
        return $this->items;
    }
}
