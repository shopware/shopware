<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Cart;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\CheckoutContext;

interface CartPersisterInterface
{
    /**
     * @param string $token
     * @param string $name
     *
     * @throws CartTokenNotFoundException
     *
     * @return Cart
     */
    public function load(string $token, string $name, CheckoutContext $context): Cart;

    public function save(Cart $cart, CheckoutContext $context): void;

    public function delete(string $token, ?string $name = null, CheckoutContext $context): void;
}
