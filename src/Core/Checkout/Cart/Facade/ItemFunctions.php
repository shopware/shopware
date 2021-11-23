<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsAddTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsCountTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsGetTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsHasTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsRemoveTrait;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

class ItemFunctions
{
    use ItemsAddTrait;
    use ItemsHasTrait;
    use ItemsRemoveTrait;
    use ItemsCountTrait;
    use ItemsGetTrait;

    /**
     * @internal
     */
    public function __construct(LineItemCollection $items, Services $services)
    {
        $this->items = $items;
        $this->services = $services;
    }

    protected function getItems(): LineItemCollection
    {
        return $this->items;
    }
}
