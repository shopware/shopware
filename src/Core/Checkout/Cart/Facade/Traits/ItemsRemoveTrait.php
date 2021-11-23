<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

/**
 * @internal
 */
trait ItemsRemoveTrait
{
    protected LineItemCollection $items;

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
