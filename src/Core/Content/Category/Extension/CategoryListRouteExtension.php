<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Extension;

use Shopware\Core\Content\Category\SalesChannel\CategoryListRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CategoryListRouteResponse>
 */
#[Package('discovery')]
final class CategoryListRouteExtension extends Extension
{
    public const NAME = 'category-list-route.load';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Criteria $criteria,
        public readonly SalesChannelContext $context,
    ) {
    }
}
