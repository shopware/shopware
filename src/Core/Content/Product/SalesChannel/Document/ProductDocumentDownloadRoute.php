<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Document;

use Shopware\Core\Content\Media\File\DownloadResponseGenerator;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('inventory')]
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
        methods: [Request::METHOD_GET],
        priority: 1
    )]
    public function load(string $productId, string $documentId, Request $request, SalesChannelContext $context): Response
    {
        $criteria = new Criteria();
        $criteria->setTitle('product-document-download-route');

        $documentsCriteria = $criteria->getAssociation('productDocuments');
        $documentsCriteria->addAssociation('media');
        $documentsCriteria->addSorting(new FieldSorting('position'));

        $productRequest = $request->duplicate(
            array_replace($request->query->all(), [
                'skipCmsPage' => '1',
                'skipConfigurator' => '1',
            ]),
            null,
            array_replace($request->attributes->all(), [
                'productId' => $productId,
            ])
        );

        $product = $this->productDetailRoute->load($productId, $productRequest, $context, $criteria)->getProduct();
        $productDocuments = $product->getProductDocuments();
        $productDocument = $productDocuments?->get($documentId);

        if ($productDocument === null || $productDocument->getMedia() === null) {
            throw ProductException::productDocumentNotFound($documentId);
        }

        return $this->downloadResponseGenerator->getResponse($productDocument->getMedia(), $context, forceDownload: true);
    }
}
