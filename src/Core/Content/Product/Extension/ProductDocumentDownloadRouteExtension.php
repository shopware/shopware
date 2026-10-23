<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Extension;

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
#[Package('inventory')]
final class ProductDocumentDownloadRouteExtension extends Extension
{
    public const NAME = 'product-document-download-route.load';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $productId,
        public readonly string $documentId,
        public readonly Request $request,
        public readonly SalesChannelContext $context,
    ) {
    }
}
