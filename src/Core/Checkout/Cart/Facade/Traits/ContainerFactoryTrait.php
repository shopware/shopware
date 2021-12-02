<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\Facade\CartFacadeHelper;
use Shopware\Core\Checkout\Cart\Facade\ContainerFacade;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal The trait is not intended for re-usability in other domains
 */
trait ContainerFactoryTrait
{
    protected LineItemCollection $items;

    protected CartFacadeHelper $helper;

    protected SalesChannelContext $context;

    /**
     * @public-api used for app scripting
     */
    public function container(string $id, ?string $label = null): ContainerFacade
    {
        $item = new LineItem($id, LineItem::CONTAINER_LINE_ITEM, $id);
        $item->setLabel($label);
        $item->setRemovable(true);
        $item->setStackable(false);

        return new ContainerFacade($item, $this->helper, $this->context);
    }

    /**
     * @internal
     */
    protected function getItems(): LineItemCollection
    {
        return $this->items;
    }
}
