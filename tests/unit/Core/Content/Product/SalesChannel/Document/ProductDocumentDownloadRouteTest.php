<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\DownloadResponseGenerator;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\Document\ProductDocumentDownloadRoute;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentDownloadRoute::class)]
class ProductDocumentDownloadRouteTest extends TestCase
{
    public function testGetDecoratedThrowsException(): void
    {
        $route = new ProductDocumentDownloadRoute(
            static::createStub(AbstractProductDetailRoute::class),
            static::createStub(DownloadResponseGenerator::class),
        );

        static::expectExceptionObject(new DecorationPatternException(ProductDocumentDownloadRoute::class));

        $route->getDecorated();
    }

    public function testLoadReturnsDownloadResponse(): void
    {
        $productId = 'product-id';
        $documentId = 'document-id';
        $request = new Request(['search' => 'query-value'], [], ['existingAttribute' => 'attribute-value']);
        $context = static::createStub(SalesChannelContext::class);
        $media = new MediaEntity();
        $media->setId('media-id');

        $product = new SalesChannelProductEntity();
        $product->setProductDocuments(new ProductDocumentCollection([
            $this->createProductDocument($documentId, $media),
        ]));

        $productDetailRoute = $this->createMock(AbstractProductDetailRoute::class);
        $productDetailRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                $productId,
                static::callback(function (Request $productRequest) use ($productId): bool {
                    // incoming query parameters like `search` must not leak into the detail request,
                    // they would influence the variant resolution and therefore the served documents
                    static::assertSame(
                        ['skipCmsPage' => '1', 'skipConfigurator' => '1'],
                        $productRequest->query->all()
                    );
                    static::assertSame('attribute-value', $productRequest->attributes->get('existingAttribute'));
                    static::assertSame($productId, $productRequest->attributes->get('productId'));

                    return true;
                }),
                $context,
                static::callback(function (Criteria $criteria): bool {
                    static::assertTrue($criteria->hasAssociation('productDocuments'));

                    $productDocumentsCriteria = $criteria->getAssociation('productDocuments');
                    static::assertTrue($productDocumentsCriteria->hasAssociation('media'));

                    // the route looks the document up by id, no sorting needed
                    static::assertSame([], $productDocumentsCriteria->getSorting());

                    return true;
                }),
            )
            ->willReturn(new ProductDetailRouteResponse($product, null));

        $response = new Response('download');
        $downloadResponseGenerator = $this->createMock(DownloadResponseGenerator::class);
        $downloadResponseGenerator
            ->expects($this->once())
            ->method('getResponse')
            ->with($media, $context)
            ->willReturn($response);

        $route = new ProductDocumentDownloadRoute($productDetailRoute, $downloadResponseGenerator);

        static::assertSame($response, $route->load($productId, $documentId, $request, $context));
    }

    public function testLoadThrowsWhenDocumentsAssociationIsNotLoaded(): void
    {
        $documentId = 'document-id';

        $route = new ProductDocumentDownloadRoute(
            $this->createProductDetailRoute(new SalesChannelProductEntity()),
            $this->createDownloadResponseGeneratorExpectingNoDownload(),
        );

        static::expectExceptionObject(ProductException::productDocumentNotFound($documentId));

        $route->load('product-id', $documentId, new Request(), static::createStub(SalesChannelContext::class));
    }

    public function testLoadThrowsWhenDocumentDoesNotBelongToProduct(): void
    {
        $documentId = 'missing-document-id';
        $product = new SalesChannelProductEntity();
        $product->setProductDocuments(new ProductDocumentCollection([
            $this->createProductDocument('other-document-id', $this->createMedia('other-media-id')),
        ]));

        $route = new ProductDocumentDownloadRoute(
            $this->createProductDetailRoute($product),
            $this->createDownloadResponseGeneratorExpectingNoDownload(),
        );

        static::expectExceptionObject(ProductException::productDocumentNotFound($documentId));

        $route->load('product-id', $documentId, new Request(), static::createStub(SalesChannelContext::class));
    }

    public function testLoadThrowsWhenDocumentHasNoMedia(): void
    {
        $documentId = 'document-without-media-id';
        $product = new SalesChannelProductEntity();
        $product->setProductDocuments(new ProductDocumentCollection([
            $this->createProductDocument($documentId),
        ]));

        $route = new ProductDocumentDownloadRoute(
            $this->createProductDetailRoute($product),
            $this->createDownloadResponseGeneratorExpectingNoDownload(),
        );

        static::expectExceptionObject(ProductException::productDocumentNotFound($documentId));

        $route->load('product-id', $documentId, new Request(), static::createStub(SalesChannelContext::class));
    }

    private function createProductDetailRoute(SalesChannelProductEntity $product): AbstractProductDetailRoute
    {
        $productDetailRoute = static::createStub(AbstractProductDetailRoute::class);
        $productDetailRoute
            ->method('load')
            ->willReturn(new ProductDetailRouteResponse($product, null));

        return $productDetailRoute;
    }

    private function createDownloadResponseGeneratorExpectingNoDownload(): DownloadResponseGenerator
    {
        $downloadResponseGenerator = $this->createMock(DownloadResponseGenerator::class);
        $downloadResponseGenerator
            ->expects($this->never())
            ->method('getResponse');

        return $downloadResponseGenerator;
    }

    private function createProductDocument(string $id, ?MediaEntity $media = null): ProductDocumentEntity
    {
        $productDocument = new ProductDocumentEntity();
        $productDocument->setId($id);
        $productDocument->setMediaId($media?->getId() ?? 'media-id');

        if ($media instanceof MediaEntity) {
            $productDocument->setMedia($media);
        }

        return $productDocument;
    }

    private function createMedia(string $id): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId($id);

        return $media;
    }
}
