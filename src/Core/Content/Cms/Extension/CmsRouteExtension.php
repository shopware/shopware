<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Extension;

use Shopware\Core\Content\Cms\SalesChannel\CmsRouteResponse;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CmsRouteResponse>
 */
#[Package('discovery')]
final class CmsRouteExtension extends Extension
{
    public const NAME = 'cms-route.load';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $id,
        public readonly Request $request,
        public readonly SalesChannelContext $context,
    ) {
    }
}
