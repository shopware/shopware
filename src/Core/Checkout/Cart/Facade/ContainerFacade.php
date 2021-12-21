<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\Facade\Traits\DiscountTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsCountTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsGetTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsHasTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsIteratorTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsRemoveTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\SurchargeTrait;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ContainerFacade extends ItemFacade
{
    use DiscountTrait;
    use SurchargeTrait;
    use ItemsGetTrait;
    use ItemsRemoveTrait;
    use ItemsHasTrait;
    use ItemsCountTrait;
    use ItemsIteratorTrait;

    private LineItem $item;

    private CartFacadeHelper $helper;

    private SalesChannelContext $context;

    /**
     * @internal
     */
    public function __construct(LineItem $item, CartFacadeHelper $helper, SalesChannelContext $context)
    {
        parent::__construct($item, $helper, $context);

        $this->item = $item;
        $this->helper = $helper;
        $this->context = $context;
    }

    public function products(): ProductsFacade
    {
        return new ProductsFacade($this->item->getChildren(), $this->helper, $this->context);
    }

    public function add(ItemFacade $item): ?ItemFacade
    {
        $this->item->getChildren()->add($item->getItem());

        return $this->get($item->getId());
    }

    protected function getItems(): LineItemCollection
    {
        // switch items pointer to children. Used for Items*Traits and DiscountTrait
        return $this->item->getChildren();
    }
}
