<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsAddTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsCountTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsGetTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsHasTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsIteratorTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsRemoveTrait;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * The ItemsFacade is a wrapper around a collection of line-items.
 *
 * @script-service cart_manipulation
 *
 * @implements \IteratorAggregate<array-key, LineItem>
 */
class ItemsFacade implements \IteratorAggregate
{
    use ItemsAddTrait;
    use ItemsHasTrait;
    use ItemsRemoveTrait;
    use ItemsCountTrait;
    use ItemsGetTrait;
    use ItemsIteratorTrait;

    /**
     * @internal
     */
    public function __construct(LineItemCollection $items, CartFacadeHelper $helper, SalesChannelContext $context)
    {
        $this->items = $items;
        $this->helper = $helper;
        $this->context = $context;
    }

    private function getItems(): LineItemCollection
    {
        return $this->items;
    }
}
