<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\Facade\CartFacadeHelper;
use Shopware\Core\Checkout\Cart\Facade\ContainerFacade;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

/**
 * @internal The trait is not intended for re-usability in other domains
 */
trait ContainerFactoryTrait
{
    protected LineItemCollection $items;

    protected CartFacadeHelper $services;

    /**
     * @public-api used for app scripting
     */
    public function container(string $id, ?string $label = null, ?string $coverId = null): ContainerFacade
    {
        $item = new LineItem($id, LineItem::CONTAINER_LINE_ITEM, $id);
        $item->setLabel($label);
        $item->setRemovable(true);
        $item->setGood(false);
        $item->setStackable(false);

        return new ContainerFacade($item, $this->services);
    }

    /**
     * @internal
     */
    protected function getItems(): LineItemCollection
    {
        return $this->items;
    }
}
