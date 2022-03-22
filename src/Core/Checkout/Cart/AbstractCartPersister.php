<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractCartPersister implements CartPersisterInterface
{
    abstract public function getDecorated(): AbstractCartPersister;

    abstract public function load(string $token, SalesChannelContext $context): Cart;

    abstract public function save(Cart $cart, SalesChannelContext $context): void;

    abstract public function delete(string $token, SalesChannelContext $context): void;

    abstract public function replace(string $oldToken, string $newToken, SalesChannelContext $context): void;
}
