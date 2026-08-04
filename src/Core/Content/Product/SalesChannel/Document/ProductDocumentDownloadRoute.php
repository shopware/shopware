<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Document;

use Shopware\Core\Content\Media\File\DownloadResponseGenerator;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ProductDocumentDownloadRoute extends AbstractProductDocumentDownloadRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractProductDetailRoute $productDetailRoute,
        private readonly DownloadResponseGenerator $downloadResponseGenerator,
    ) {
    }

    public function getDecorated(): AbstractProductDocumentDownloadRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/product/{productId}/document/{documentId}/download',
        name: 'store-api.product.document.download',
        methods: [Request::METHOD_GET]
    )]
    public function load(string $productId, string $documentId, Request $request, SalesChannelContext $context): Response
    {
        $criteria = $this->createProductCriteria();
        $productRequest = $this->createProductDetailRequest($request, $productId);

        $product = $this->productDetailRoute->load($productId, $productRequest, $context, $criteria)->getProduct();
        $productDocuments = $product->getProductDocuments();
        $productDocument = $productDocuments?->get($documentId);

        if ($productDocument === null || $productDocument->getMedia() === null) {
            throw ProductException::productDocumentNotFound($documentId);
        }

        return $this->downloadResponseGenerator->getResponse($productDocument->getMedia(), $context);
    }

    private function createProductCriteria(): Criteria
    {
        $criteria = new Criteria();

        $criteria->getAssociation('productDocuments')->addAssociation('media');

        return $criteria;
    }

    private function createProductDetailRequest(Request $request, string $productId): Request
    {
        return $request->duplicate(
            [
                'skipCmsPage' => '1',
                'skipConfigurator' => '1',
            ],
            null,
            array_replace($request->attributes->all(), [
                'productId' => $productId,
            ])
        );
    }
}
