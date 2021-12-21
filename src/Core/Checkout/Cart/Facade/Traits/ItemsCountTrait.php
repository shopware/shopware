<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

trait ItemsCountTrait
{
    private LineItemCollection $items;

    public function count(): int
    {
        return $this->getItems()->count();
    }

    private function getItems(): LineItemCollection
    {
        return $this->items;
    }
}
