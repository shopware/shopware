<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

/**
 * @internal The trait is not intended for re-usability in other domains
 */
trait ItemsRemoveTrait
{
    protected LineItemCollection $items;

    /**
     * @public-api used for app scripting
     */
    public function remove(string $id): void
    {
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
