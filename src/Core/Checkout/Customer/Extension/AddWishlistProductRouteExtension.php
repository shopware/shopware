<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Extension;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<SuccessResponse>
 */
#[Package('checkout')]
final class AddWishlistProductRouteExtension extends Extension
{
    public const NAME = 'add-wishlist-product-route.add';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $productId,
        public readonly SalesChannelContext $context,
        public readonly CustomerEntity $customer,
    ) {
    }
}
