<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Extension;

use Shopware\Core\Content\Product\SalesChannel\Garan\GaranLabelRouteResponse;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<GaranLabelRouteResponse>
 */
#[Package('inventory')]
final class GaranLabelRouteExtension extends Extension
{
    public const NAME = 'garan-label-route.load';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $productId,
        public readonly SalesChannelContext $context,
    ) {
    }
}
