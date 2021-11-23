<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

/**
 * @internal
 */
trait ItemsHasTrait
{
    protected LineItemCollection $items;

    public function has(string $id): bool
    {
        return $this->getItems()->has($id);
    }

    /**
     * @internal
     */
    protected function getItems(): LineItemCollection
    {
        return $this->items;
    }
}
