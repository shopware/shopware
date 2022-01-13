<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Facade\Traits\ContainerFactoryTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\DiscountTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsCountTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsGetTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsHasTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsRemoveTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\SurchargeTrait;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * The `cart` service allows you to manipulate the cart.
 * You can use the cart service to add line-items, change prices, add discounts, etc. to the cart.
 *
 * @script-service cart_manipulation
 */
class CartFacade
{
    use DiscountTrait;
    use SurchargeTrait;
    use ItemsGetTrait;
    use ItemsRemoveTrait;
    use ItemsHasTrait;
    use ItemsCountTrait;
    use ContainerFactoryTrait;

    private Cart $cart;

    /**
     * @internal
     */
    public function __construct(CartFacadeHelper $helper, Cart $cart, SalesChannelContext $context)
    {
        $this->helper = $helper;
        $this->cart = $cart;
        $this->context = $context;
    }

    /**
     * The `items()` method returns all line-items of the current cart for further manipulation.
     *
     * @return ItemsFacade A `ItemsFacade` containing all line-items in the current cart as a collection.
     */
    public function items(): ItemsFacade
    {
        return new ItemsFacade($this->cart->getLineItems(), $this->helper, $this->context);
    }

    /**
     * The `product()` method returns all products of the current cart for further manipulation.
     * Similar to the `items()` method, but the line-items are filtered, to only contain product line items.
     *
     * @return ProductsFacade A `ProductsFacade` containing all product line-items in the current cart as a collection.
     */
    public function products(): ProductsFacade
    {
        return new ProductsFacade($this->cart->getLineItems(), $this->helper, $this->context);
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
            throw new \LogicException('Cart behavior missing. The instanced cart was never calculated');
        }

        $this->cart = $behavior->disableHooks(function () use ($behavior) {
            return $this->helper->calculate($this->cart, $behavior, $this->context);
        });
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
        return new CartPriceFacade($this->cart->getPrice(), $this->helper);
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
     * `addState()` allows you to add one or multiple states as string values to the cart.
     * This can be useful to check if your script did already run and did some manipulations to the cart.
     *
     * @param string ...$states One or more strings that will be stored on the cart.
     */
    public function addState(string ...$states): void
    {
        $this->cart->addState(...$states);
    }

    /**
     * `removeState()` removes the given state from the cart, if it existed.
     *
     * @param string $state The state that should be removed.
     */
    public function removeState(string $state): void
    {
        $this->cart->removeState($state);
    }

    /**
     * `hasState()` allows you to check if one or more states are present on the cart.
     *
     * @param string ...$states One or more strings that should be checked.
     *
     * @return bool Returns true if at least one of the passed states is present on the cart, false otherwise.
     */
    public function hasState(string ...$states): bool
    {
        return $this->cart->hasState(...$states);
    }

    /**
     * `getStates()` returns all states that are present on the cart.
     *
     * @return array An array containing all current states of the cart.
     */
    public function getStates(): array
    {
        return $this->cart->getStates();
    }

    private function getItems(): LineItemCollection
    {
        return $this->cart->getLineItems();
    }
}
