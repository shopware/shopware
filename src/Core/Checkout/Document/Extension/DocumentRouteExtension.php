<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Extension;

use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<Response>
 */
#[Package('after-sales')]
final class DocumentRouteExtension extends Extension
{
    public const NAME = 'document-route.download';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $documentId,
        public readonly Request $request,
        public readonly SalesChannelContext $context,
        public readonly string $deepLinkCode,
        public readonly ?string $fileType,
        public readonly ?string $format,
    ) {
    }
}
