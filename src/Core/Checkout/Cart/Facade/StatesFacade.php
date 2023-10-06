<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;

/**
 * The StatesFacade allows access to the current cart states and functions.
 *
 * @script-service cart_manipulation
 */
#[Package('checkout')]
class StatesFacade
{
    public function __construct(private Cart $cart)
    {
    }

    /**
     * `add()` allows you to add one or multiple states as string values to the cart.
     * This can be useful to check if your script did already run and did some manipulations to the cart.
     *
     * @param string ...$states One or more strings that will be stored on the cart.
     */
    public function add(string ...$states): void
    {
        $this->cart->addState(...$states);
    }

    /**
     * `remove()` removes the given state from the cart, if it existed.
     *
     * @param string $state The state that should be removed.
     */
    public function remove(string $state): void
    {
        $this->cart->removeState($state);
    }

    /**
     * `has()` allows you to check if one or more states are present on the cart.
     *
     * @param string ...$states One or more strings that should be checked.
     *
     * @return bool Returns true if at least one of the passed states is present on the cart, false otherwise.
     */
    public function has(string ...$states): bool
    {
        return $this->cart->hasState(...$states);
    }

    /**
     * `get()` returns all states that are present on the cart.
     *
     * @return array<string> An array containing all current states of the cart.
     */
    public function get(): array
    {
        return $this->cart->getStates();
    }
}
