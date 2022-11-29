<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

/**
 * @package checkout
 */
trait ItemsCountTrait
{
    private LineItemCollection $items;

    /**
     * `count()` returns the count of line-items in this collection.
     * Note that it does only count the line-items directly in this collection and not child line-items of those.
     *
     * @return int The number of line-items in this collection.
     */
    public function count(): int
    {
        return $this->getItems()->count();
    }

    private function getItems(): LineItemCollection
    {
        return $this->items;
    }
}
