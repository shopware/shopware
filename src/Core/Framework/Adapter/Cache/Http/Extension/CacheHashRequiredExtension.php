<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http\Extension;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @extends Extension<bool>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class CacheHashRequiredExtension extends Extension
{
    public const NAME = 'cache-hash.required';

    /**
     * @internal Shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The HTTP request object
         */
        public readonly Request $request,

        /**
         * @public
         *
         * @description The sales channel context
         */
        public readonly SalesChannelContext $salesChannelContext,

        /**
         * @public
         *
         * @description The current cart
         */
        public readonly Cart $cart,
    ) {
    }
}
