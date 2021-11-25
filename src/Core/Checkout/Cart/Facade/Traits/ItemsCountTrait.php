<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

/**
 * @internal The trait is not intended for re-usability in other domains
 */
trait ItemsCountTrait
{
    protected LineItemCollection $items;

    /**
     * @public-api used for app scripting
     */
    public function count(): int
    {
        return $this->getItems()->count();
    }

    /**
     * @internal
     */
    protected function getItems(): LineItemCollection
    {
        return $this->items;
    }
}
