<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Facade\Traits\ContainerFactoryTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\DiscountTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsCountTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsGetTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsHasTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsRemoveTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\SurchargeTrait;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * The `cart` service allows you to manipulate the cart.
 * You can use the cart service to add line-items, change prices, add discounts, etc. to the cart.
 *
 * @script-service cart_manipulation
 */
#[Package('checkout')]
class CartFacade
{
    use DiscountTrait;
    use SurchargeTrait;
    use ItemsGetTrait;
    use ItemsRemoveTrait;
    use ItemsHasTrait;
    use ItemsCountTrait;
    use ContainerFactoryTrait;

    /**
     * @internal
     */
    public function __construct(
        private CartFacadeHelper $helper,
        private ScriptPriceStubs $priceStubs,
        private Cart $cart,
        private SalesChannelContext $context
    ) {
    }

    /**
     * The `items()` method returns all line-items of the current cart for further manipulation.
     *
     * @return ItemsFacade A `ItemsFacade` containing all line-items in the current cart as a collection.
     */
    public function items(): ItemsFacade
    {
        return new ItemsFacade($this->cart->getLineItems(), $this->priceStubs, $this->helper, $this->context);
    }

    /**
     * The `product()` method returns all products of the current cart for further manipulation.
     * Similar to the `items()` method, but the line-items are filtered, to only contain product line items.
     *
     * @return ProductsFacade A `ProductsFacade` containing all product line-items in the current cart as a collection.
     */
    public function products(): ProductsFacade
    {
        return new ProductsFacade($this->cart->getLineItems(), $this->priceStubs, $this->helper, $this->context);
    }

    /**
     * The `calculate()` method recalculates the whole cart.
     * Use this to get the correct prices after you made changes to the cart.
     * Note that after calling the `calculate()` all collections (e.g. items(), products()) get new references,
     * so if you still hold references to things inside the cart, these are outdated after calling `calculate()`.
     *
     * The `calculate()` method will be called automatically after your cart script executed.
     */
    public function calculate(): void
    {
        $behavior = $this->cart->getBehavior();
        if (!$behavior) {
            throw CartException::missingCartBehavior();
        }

        $this->cart = $behavior->disableHooks(fn () => $this->helper->calculate($this->cart, $behavior, $this->context));
    }

    /**
     * The `price()` method returns the current price of the cart.
     * Note that this price may be outdated, if you changed something inside the cart in your script.
     * Use the `calculate()` method to recalculate the cart and update the price.
     *
     * @return CartPriceFacade The calculated price of the cart.
     */
    public function price(): CartPriceFacade
    {
        return new CartPriceFacade($this->cart->getPrice(), $this->priceStubs);
    }

    /**
     * The `errors()` method returns the current errors of the cart.
     * You can use it to add new errors or warning or to remove existing ones.
     *
     * @return ErrorsFacade A `ErrorsFacade` containing all cart errors as a collection (may be an empty collection if there are no errors)
     */
    public function errors(): ErrorsFacade
    {
        return new ErrorsFacade($this->cart->getErrors());
    }

    /**
     * `states()` allows you to access the state functions of the current cart.
     *
     * @return StatesFacade A `StatesFacade` containing all cart states as a collection (maybe an empty collection if there are no states)
     */
    public function states(): StatesFacade
    {
        return new StatesFacade($this->cart);
    }

    private function getItems(): LineItemCollection
    {
        return $this->cart->getLineItems();
    }
}
