<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\Facade\Traits\PriceFactoryTrait;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;

/**
 * The CartPriceFacade is a wrapper around the calculated price of a cart.
 *
 * @script-service cart_manipulation
 */
class CartPriceFacade
{
    use PriceFactoryTrait;

    private CartPrice $price;

    /**
     * @internal
     */
    public function __construct(CartPrice $price, CartFacadeHelper $helper)
    {
        $this->price = $price;
        $this->helper = $helper;
    }

    /**
     * `getNet()` returns the net price of the cart.
     *
     * @return float Returns the net price of the cart as float.
     */
    public function getNet(): float
    {
        return $this->price->getNetPrice();
    }

    /**
     * `getTotal()` returns the total price of the cart that has to be paid by the customer.
     * Depending on the tax settings this may be the gross or net price.
     * Note that this price is already rounded, to get the raw price before rounding use `getRaw()`.
     *
     * @return float The rounded total price of the cart as float.
     */
    public function getTotal(): float
    {
        return $this->price->getTotalPrice();
    }

    /**
     * `getPosition()` returns the sum price of all line-items in the cart.
     * In the position price the shipping costs are excluded.
     * Depending on the tax settings this may be the gross or net price og the line-items.
     *
     * @return float The position price as float.
     */
    public function getPosition(): float
    {
        return $this->price->getPositionPrice();
    }

    /**
     * Alias for `getTotal()`.
     *
     * @return float The rounded total price of the cart as float.
     */
    public function getRounded(): float
    {
        return $this->getTotal();
    }

    /**
     * `getRaw() returns the total price of the cart before rounding.
     *
     * @return float The total price before rounding as float.
     */
    public function getRaw(): float
    {
        return $this->price->getRawTotal();
    }
}
