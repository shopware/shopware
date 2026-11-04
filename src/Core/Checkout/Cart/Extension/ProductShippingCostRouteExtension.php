<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Extension;

use Shopware\Core\Checkout\Cart\SalesChannel\ShippingCostRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ShippingCostRouteResponse>
 */
#[Package('checkout')]
final class ProductShippingCostRouteExtension extends Extension
{
    public const NAME = 'product-shipping-cost-route.shipping-costs-by-product';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $productId,
        public readonly Criteria $criteria,
        public readonly SalesChannelContext $salesChannelContext,
    ) {
    }
}
