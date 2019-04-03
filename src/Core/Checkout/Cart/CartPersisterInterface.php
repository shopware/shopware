<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CartPersisterInterface
{
    /**
     * @throws CartTokenNotFoundException
     */
    public function load(string $token, SalesChannelContext $context): Cart;

    public function save(Cart $cart, SalesChannelContext $context): void;

    public function delete(string $token, SalesChannelContext $context): void;
}
