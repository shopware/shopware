<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SalesChannel\Document;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Document\ProductDocumentDownloadRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentDownloadRouteTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private MediaService $mediaService;

    private ProductDocumentDownloadRoute $route;

    private Context $context;

    protected function setUp(): void
    {
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->mediaService = static::getContainer()->get(MediaService::class);
        $this->route = static::getContainer()->get(ProductDocumentDownloadRoute::class);
        $this->context = Context::createDefaultContext();
    }

    public function testDownloadProductDocumentForProduct(): void
    {
        $productId = Uuid::randomHex();
        $documentId = Uuid::randomHex();
        $mediaId = $this->createProductDocumentMedia();

        $this->createVisibleProduct($productId, [[
            'id' => $documentId,
            'mediaId' => $mediaId,
            'position' => 1,
        ]]);

        $response = $this->route->load(
            $productId,
            $documentId,
            new Request(),
            $this->createSalesChannelContext(),
        );

        static::assertContains($response->getStatusCode(), [Response::HTTP_OK, Response::HTTP_FOUND, Response::HTTP_SEE_OTHER, Response::HTTP_TEMPORARY_REDIRECT]);
    }

    public function testDownloadThrowsWhenDocumentDoesNotBelongToProduct(): void
    {
        $productId = Uuid::randomHex();
        $otherProductId = Uuid::randomHex();
        $documentId = Uuid::randomHex();
        $mediaId = $this->createProductDocumentMedia();

        $this->createVisibleProduct($productId, [[
            'id' => $documentId,
            'mediaId' => $mediaId,
            'position' => 1,
        ]]);
        $this->createVisibleProduct($otherProductId);

        static::expectExceptionObject(ProductException::productDocumentNotFound($documentId));

        $this->route->load(
            $otherProductId,
            $documentId,
            new Request(),
            $this->createSalesChannelContext(),
        );
    }

    public function testDownloadInheritedProductDocumentForVariant(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();
        $documentId = Uuid::randomHex();
        $mediaId = $this->createProductDocumentMedia();

        $this->productRepository->create([[
            'id' => $parentId,
            'name' => 'Parent product',
            'productNumber' => Uuid::randomHex(),
            'active' => true,
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test tax', 'taxRate' => 15],
            'visibilities' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
            'productDocuments' => [[
                'id' => $documentId,
                'mediaId' => $mediaId,
                'position' => 1,
            ]],
            'children' => [[
                'id' => $variantId,
                'productNumber' => Uuid::randomHex(),
                'name' => 'Variant product',
                'active' => true,
                'stock' => 10,
            ]],
        ]], $this->context);

        $response = $this->route->load(
            $variantId,
            $documentId,
            new Request(),
            $this->createSalesChannelContext(),
        );

        static::assertContains($response->getStatusCode(), [Response::HTTP_OK, Response::HTTP_FOUND, Response::HTTP_SEE_OTHER, Response::HTTP_TEMPORARY_REDIRECT]);
    }

    /**
     * @param list<array{id: string, mediaId: string, position: int}> $productDocuments
     */
    private function createVisibleProduct(string $productId, array $productDocuments = []): void
    {
        $product = [
            'id' => $productId,
            'name' => 'Test product',
            'productNumber' => Uuid::randomHex(),
            'active' => true,
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test tax', 'taxRate' => 15],
            'visibilities' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        if ($productDocuments !== []) {
            $product['productDocuments'] = $productDocuments;
        }

        $this->productRepository->create([$product], $this->context);
    }

    private function createProductDocumentMedia(): string
    {
        return $this->mediaService->saveFile(
            "sku,manual\nproduct,document\n",
            'csv',
            'text/csv',
            'product-document-' . Uuid::randomHex(),
            $this->context,
            'product_document',
            null,
            true,
        );
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        return static::getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }
}
