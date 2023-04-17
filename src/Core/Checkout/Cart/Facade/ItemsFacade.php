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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * The ItemsFacade is a wrapper around a collection of line-items.
 *
 * @script-service cart_manipulation
 *
 * @implements \IteratorAggregate<array-key, LineItem>
 */
#[Package('checkout')]
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
    public function __construct(
        private LineItemCollection $items,
        private ScriptPriceStubs $priceStubs,
        private CartFacadeHelper $helper,
        private SalesChannelContext $context
    ) {
    }

    private function getItems(): LineItemCollection
    {
        return $this->items;
    }
}
