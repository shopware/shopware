<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

/**
 * @internal
 */
trait ItemsCountTrait
{
    protected LineItemCollection $items;

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
