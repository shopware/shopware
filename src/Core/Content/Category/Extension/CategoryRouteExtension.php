<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Extension;

use Shopware\Core\Content\Category\SalesChannel\CategoryRouteResponse;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CategoryRouteResponse>
 */
#[Package('discovery')]
final class CategoryRouteExtension extends Extension
{
    public const NAME = 'category-route.load';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $navigationId,
        public readonly Request $request,
        public readonly SalesChannelContext $context,
    ) {
    }
}
