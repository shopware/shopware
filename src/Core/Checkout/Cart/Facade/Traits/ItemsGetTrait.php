<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\Facade\CartFacadeHelper;
use Shopware\Core\Checkout\Cart\Facade\ContainerFacade;
use Shopware\Core\Checkout\Cart\Facade\ItemFacade;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal The trait is not intended for re-usability in other domains
 */
trait ItemsGetTrait
{
    protected LineItemCollection $items;

    protected CartFacadeHelper $helper;

    protected SalesChannelContext $context;

    /**
     * @public-api used for app scripting
     */
    public function get(string $id): ?ItemFacade
    {
        $item = $this->getItems()->get($id);

        if (!$item instanceof LineItem) {
            return null;
        }

        if ($item->getType() === 'container') {
            return new ContainerFacade($item, $this->helper, $this->context);
        }

        return new ItemFacade($item, $this->helper, $this->context);
    }

    /**
     * @internal
     */
    protected function getItems(): LineItemCollection
    {
        return $this->items;
    }
}
