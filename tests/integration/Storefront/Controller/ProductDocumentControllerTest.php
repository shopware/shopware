<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private const SUCCESSFUL_DOWNLOAD_STATUS_CODES = [
        Response::HTTP_OK,
        Response::HTTP_FOUND,
        Response::HTTP_SEE_OTHER,
        Response::HTTP_TEMPORARY_REDIRECT,
    ];

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private MediaService $mediaService;

    private Context $context;

    protected function setUp(): void
    {
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->mediaService = static::getContainer()->get(MediaService::class);
        $this->context = Context::createDefaultContext();
    }

    public function testDownloadServesProductDocument(): void
    {
        $productId = Uuid::randomHex();
        $documentId = Uuid::randomHex();

        $this->createVisibleProduct($productId, [[
            'id' => $documentId,
            'mediaId' => $this->createProductDocumentMedia(),
            'position' => 1,
        ]]);

        $response = $this->request('GET', \sprintf('/product/%s/document/%s/download', $productId, $documentId), []);

        static::assertContains($response->getStatusCode(), self::SUCCESSFUL_DOWNLOAD_STATUS_CODES);
    }

    public function testDownloadOfUnknownDocumentReturns404(): void
    {
        $productId = Uuid::randomHex();
        $unknownDocumentId = Uuid::randomHex();

        $this->createVisibleProduct($productId);

        $response = $this->request('GET', \sprintf('/product/%s/document/%s/download', $productId, $unknownDocumentId), []);

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
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
                ['salesChannelId' => $this->getSalesChannelId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
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
}
