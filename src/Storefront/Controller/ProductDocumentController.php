<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Product\SalesChannel\Document\AbstractProductDocumentDownloadRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class ProductDocumentController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractProductDocumentDownloadRoute $productDocumentDownloadRoute)
    {
    }

    #[Route(
        path: '/product/{productId}/document/{documentId}/download',
        name: 'frontend.product.document.download',
        methods: [Request::METHOD_GET]
    )]
    public function download(string $productId, string $documentId, Request $request, SalesChannelContext $context): Response
    {
        return $this->productDocumentDownloadRoute->load($productId, $documentId, $request, $context);
    }
}
