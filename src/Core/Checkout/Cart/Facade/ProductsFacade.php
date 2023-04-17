<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

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
 * The ProductsFacade is a wrapper around a collection of product line-items.
 *
 * @script-service cart_manipulation
 *
 * @implements \IteratorAggregate<array-key, LineItem>
 */
#[Package('checkout')]
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
    public function __construct(
        private LineItemCollection $items,
        private ScriptPriceStubs $priceStubs,
        private CartFacadeHelper $helper,
        private SalesChannelContext $context
    ) {
    }

    /**
     * `get()` returns the product line-item with the given product id.
     *
     * @param string $productId The id of the product, of which the line-item should be returned.
     *
     * @return ItemFacade|null The line-item associated with the given product id, or null if it does not exist.
     *
     * @example payload-cases/payload-cases.twig 5 1 Get a product line-item by id.
     */
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
     * `add()` adds a new product  line-item to this collection.
     * In the case only a product id is provided it will create a new line-item from type product for the given product id.
     *
     * @param string|LineItem|ItemFacade $product The product that should be added. Either an existing `ItemFacade` or `LineItem` or alternatively the id of a product.
     * @param int $quantity Optionally provide the quantity with which the product line-item should be created, defaults to 1.
     *
     * @return ItemFacade The newly added product line-item.
     *
     * @example add-product-cases/add-product-cases.twig 2 1 Add a product to the cart by id.
     */
    public function add(string|LineItem|ItemFacade $product, int $quantity = 1): ItemFacade
    {
        if ($product instanceof ItemFacade) {
            $this->items->add($product->getItem());

            /** @var ItemFacade $product */
            $product = $this->get($product->getId());

            return $product;
        }

        if ($product instanceof LineItem) {
            $this->items->add($product);

            /** @var ItemFacade $product */
            $product = $this->get($product->getId());

            return $product;
        }

        $product = $this->helper->product($product, $quantity, $this->context);

        $this->items->add($product);

        /** @var ItemFacade $product */
        $product = $this->get($product->getId());

        return $product;
    }

    /**
     * `create()` creates a new product line-item for the product with the given id in the given quantity.
     * Note that the created line-item will not be added automatically to this collection, use `add()` for that.
     *
     * @param string $productId The product id for which a line-item should be created.
     * @param int $quantity Optionally provide the quantity with which the product line-item should be created, defaults to 1.
     *
     * @return ItemFacade The newly created product line-item.
     */
    public function create(string $productId, int $quantity = 1): ItemFacade
    {
        $product = $this->helper->product($productId, $quantity, $this->context);

        return new ItemFacade($product, $this->priceStubs, $this->helper, $this->context);
    }

    private function getItems(): LineItemCollection
    {
        return $this->items;
    }
}
