<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Cart;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\CheckoutContext;

interface CartPersisterInterface
{
    /**
     * @throws CartTokenNotFoundException
     */
    public function load(string $token, CheckoutContext $context): Cart;

    public function save(Cart $cart, CheckoutContext $context): void;

    public function delete(string $token, CheckoutContext $context): void;
}
