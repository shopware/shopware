<?php declare(strict_types=1);

namespace Shopware\Core\Content\LegalGuaranteeNotice\Extension;

use Shopware\Core\Content\LegalGuaranteeNotice\SalesChannel\LegalGuaranteeNoticeRouteResponse;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<LegalGuaranteeNoticeRouteResponse>
 */
#[Package('inventory')]
final class LegalGuaranteeNoticeRouteExtension extends Extension
{
    public const NAME = 'legal-guarantee-notice-route.load';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly SalesChannelContext $context,
    ) {
    }
}
