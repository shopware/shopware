<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Extension;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\RuleLoaderResult;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 *
 * @extends Extension<RuleLoaderResult>
 */
#[Package('checkout')]
final class CheckoutCartRuleLoaderExtension extends Extension
{
    public const NAME = 'checkout.cart.rule-load';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        public readonly SalesChannelContext $salesChannelContext,
        public readonly Cart $originalCart,
        public readonly CartBehavior $cartBehavior,
        protected readonly bool $new,
    ) {
    }
}
