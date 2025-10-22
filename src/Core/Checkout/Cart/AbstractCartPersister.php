<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
abstract class AbstractCartPersister
{
    /**
     * @deprecated tag:v6.8.0 - Will be removed and is replaced by {@see CheckoutPermissions::PERSIST_CART_ERROR}
     */
    public const PERSIST_CART_ERROR_PERMISSION = CheckoutPermissions::PERSIST_CART_ERRORS;

    abstract public function getDecorated(): AbstractCartPersister;

    abstract public function load(string $token, SalesChannelContext $context): Cart;

    abstract public function save(Cart $cart, SalesChannelContext $context): void;

    abstract public function delete(string $token, SalesChannelContext $context): void;

    abstract public function replace(string $oldToken, string $newToken, SalesChannelContext $context): void;

    /**
     * This method is called by the cleanup task handler to remove old carts from the database.
     * The cart persisted should implement this method to remove carts that are older than the given amount of days.
     */
    public function prune(int $days): void
    {
    }

    protected function shouldPersist(Cart $cart): bool
    {
        return ($cart->getLineItems()->count() > 0
            || ($cart->getErrors()->count() > 0 && $cart->getBehavior()?->hasPermission(CheckoutPermissions::PERSIST_CART_ERRORS))
            || $cart->getAffiliateCode() !== null
            || $cart->getCampaignCode() !== null
            || $cart->getCustomerComment() !== null
            || $cart->getExtension(DeliveryProcessor::MANUAL_SHIPPING_COSTS) instanceof CalculatedPrice)
            && !$cart->getBehavior()?->hasPermission(CheckoutPermissions::SKIP_CART_PERSISTENCE);
    }
}
