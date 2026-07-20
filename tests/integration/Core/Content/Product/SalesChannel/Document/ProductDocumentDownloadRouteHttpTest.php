<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SalesChannel\Document;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('inventory')]
#[Group('store-api')]
class ProductDocumentDownloadRouteHttpTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const SUCCESSFUL_DOWNLOAD_STATUS_CODES = [
        Response::HTTP_OK,
        Response::HTTP_FOUND,
        Response::HTTP_SEE_OTHER,
        Response::HTTP_TEMPORARY_REDIRECT,
    ];

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private MediaService $mediaService;

    private Context $context;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = Context::createDefaultContext();
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->mediaService = static::getContainer()->get(MediaService::class);

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
    }

    public function testDownloadReturnsDocumentFile(): void
    {
        $productId = Uuid::randomHex();
        $documentId = Uuid::randomHex();

        $this->createVisibleProduct($productId, [[
            'id' => $documentId,
            'mediaId' => $this->createProductDocumentMedia(),
            'position' => 1,
        ]]);

        $this->browser->request('GET', \sprintf('/store-api/product/%s/document/%s/download', $productId, $documentId));

        $response = $this->browser->getResponse();
        static::assertContains($response->getStatusCode(), self::SUCCESSFUL_DOWNLOAD_STATUS_CODES);
    }

    public function testDownloadOfUnknownDocumentReturns404(): void
    {
        $productId = Uuid::randomHex();
        $unknownDocumentId = Uuid::randomHex();

        $this->createVisibleProduct($productId);

        $this->browser->request('GET', \sprintf('/store-api/product/%s/document/%s/download', $productId, $unknownDocumentId));

        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertIsString($response->getContent());
        static::assertStringContainsString(ProductException::PRODUCT_DOCUMENT_NOT_FOUND, $response->getContent());
    }

    public function testDownloadForInactiveProductReturns404(): void
    {
        $productId = Uuid::randomHex();
        $documentId = Uuid::randomHex();

        $this->createVisibleProduct($productId, [[
            'id' => $documentId,
            'mediaId' => $this->createProductDocumentMedia(),
            'position' => 1,
        ]], false);

        $this->browser->request('GET', \sprintf('/store-api/product/%s/document/%s/download', $productId, $documentId));

        static::assertSame(Response::HTTP_NOT_FOUND, $this->browser->getResponse()->getStatusCode());
    }

    public function testDownloadDocumentOfOtherProductReturns404(): void
    {
        $productId = Uuid::randomHex();
        $otherProductId = Uuid::randomHex();
        $documentId = Uuid::randomHex();

        $this->createVisibleProduct($productId, [[
            'id' => $documentId,
            'mediaId' => $this->createProductDocumentMedia(),
            'position' => 1,
        ]]);
        $this->createVisibleProduct($otherProductId);

        $this->browser->request('GET', \sprintf('/store-api/product/%s/document/%s/download', $otherProductId, $documentId));

        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertIsString($response->getContent());
        static::assertStringContainsString(ProductException::PRODUCT_DOCUMENT_NOT_FOUND, $response->getContent());
    }

    public function testVariantDownloadsInheritedParentDocument(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();
        $documentId = Uuid::randomHex();

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
                ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
            'productDocuments' => [[
                'id' => $documentId,
                'mediaId' => $this->createProductDocumentMedia(),
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

        $this->browser->request('GET', \sprintf('/store-api/product/%s/document/%s/download', $variantId, $documentId));

        $response = $this->browser->getResponse();
        static::assertContains($response->getStatusCode(), self::SUCCESSFUL_DOWNLOAD_STATUS_CODES);
    }

    /**
     * @param list<array{id: string, mediaId: string, position: int}> $productDocuments
     */
    private function createVisibleProduct(string $productId, array $productDocuments = [], bool $active = true): void
    {
        $product = [
            'id' => $productId,
            'name' => 'Test product',
            'productNumber' => Uuid::randomHex(),
            'active' => $active,
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test tax', 'taxRate' => 15],
            'visibilities' => [
                ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
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
