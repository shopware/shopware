<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Extension;

use Shopware\Core\Content\Media\SalesChannel\MediaRouteResponse;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<MediaRouteResponse>
 */
#[Package('discovery')]
final class MediaRouteExtension extends Extension
{
    public const NAME = 'media-route.load';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
    ) {
    }
}
