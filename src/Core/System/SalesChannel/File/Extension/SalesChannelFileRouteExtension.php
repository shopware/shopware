<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Extension;

use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<Response>
 */
#[Package('framework')]
final class SalesChannelFileRouteExtension extends Extension
{
    public const NAME = 'sales-channel-file-route.load';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $fileName,
        public readonly SalesChannelContext $context,
    ) {
    }
}
