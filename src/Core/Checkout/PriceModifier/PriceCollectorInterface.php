<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\PriceModifier;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Gathers data needed for a later PriceProcessorInterface (e.g. a voucher's configured value).
 * Tag a service with "shopware.cart.price_collector" (supports a "priority" attribute) to register
 * one. Runs once per cart calculation, alongside the regular "shopware.cart.collector" services,
 * writing into the same $data bag.
 */
#[Package('checkout')]
interface PriceCollectorInterface
{
    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void;
}
