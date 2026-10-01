<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Extension;

use Shopware\Core\Content\Product\SalesChannel\ProductListResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ProductListResponse>
 */
#[Package('inventory')]
final class ProductListRouteExtension extends Extension
{
    public const NAME = 'product-list-route.load';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Criteria $criteria,
        public readonly SalesChannelContext $context,
    ) {
    }
}
