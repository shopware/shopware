<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsCountTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsGetTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsHasTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsIteratorTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsRemoveTrait;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @implements \IteratorAggregate<array-key, \Shopware\Core\Checkout\Cart\LineItem\LineItem>
 */
class ProductsFacade implements \IteratorAggregate
{
    use ItemsGetTrait {
        ItemsGetTrait::get as private _get;
    }

    use ItemsIteratorTrait;
    use ItemsRemoveTrait;
    use ItemsHasTrait;
    use ItemsCountTrait;

    /**
     * @internal
     */
    public function __construct(LineItemCollection $items, CartFacadeHelper $helper, SalesChannelContext $context)
    {
        $this->items = $items;
        $this->helper = $helper;
        $this->context = $context;
    }

    public function get(string $productId): ?ItemFacade
    {
        $item = $this->_get($productId);

        if ($item === null) {
            return null;
        }

        if ($item->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
            return null;
        }

        return $item;
    }

    /**
     * @param string|LineItem|ItemFacade $product
     */
    public function add($product, int $quantity = 1): ?ItemFacade
    {
        if ($product instanceof ItemFacade) {
            $this->items->add($product->getItem());

            return $this->get($product->getId());
        }

        if ($product instanceof LineItem) {
            $this->items->add($product);

            return $this->get($product->getId());
        }

        $product = $this->helper->product($product, $quantity, $this->context);

        $this->items->add($product);

        return $this->get($product->getId());
    }

    public function create(string $productId, int $quantity = 1): ?ItemFacade
    {
        $product = $this->helper->product($productId, $quantity, $this->context);

        return new ItemFacade($product, $this->helper, $this->context);
    }

    protected function getItems(): LineItemCollection
    {
        return $this->items;
    }
}
