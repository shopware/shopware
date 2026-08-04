<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Aggregate\ProductDocument;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    /**
     * @var EntityRepository<ProductDocumentCollection>
     */
    private EntityRepository $productDocumentRepository;

    private Context $context;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->mediaRepository = static::getContainer()->get('media.repository');
        $this->productDocumentRepository = static::getContainer()->get('product_document.repository');
        $this->connection = static::getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testProductDocumentsCanBeWrittenAndLoadedWithMedia(): void
    {
        $productId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $documentId = Uuid::randomHex();

        $this->createMedia($mediaId);
        $this->createProduct($productId, [
            'productDocuments' => [[
                'id' => $documentId,
                'mediaId' => $mediaId,
                'position' => 5,
            ]],
        ]);

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('productDocuments.media');

        $product = $this->productRepository->search($criteria, $this->context)->getEntities()->get($productId);
        static::assertInstanceOf(ProductEntity::class, $product);

        $productDocuments = $product->getProductDocuments();
        static::assertInstanceOf(ProductDocumentCollection::class, $productDocuments);
        static::assertCount(1, $productDocuments);

        $productDocument = $productDocuments->get($documentId);
        static::assertNotNull($productDocument);
        static::assertSame($productId, $productDocument->getProductId());
        static::assertSame(Defaults::LIVE_VERSION, $productDocument->getProductVersionId());
        static::assertSame($mediaId, $productDocument->getMediaId());
        static::assertSame(5, $productDocument->getPosition());
        static::assertInstanceOf(MediaEntity::class, $productDocument->getMedia());
    }

    public function testDuplicateProductDocumentMediaAssignmentsAreRejected(): void
    {
        $productId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();

        $this->createMedia($mediaId);
        $this->createProduct($productId, [
            'productDocuments' => [[
                'id' => Uuid::randomHex(),
                'mediaId' => $mediaId,
                'position' => 1,
            ]],
        ]);

        static::expectException(UniqueConstraintViolationException::class);

        $this->productDocumentRepository->create([[
            'id' => Uuid::randomHex(),
            'productId' => $productId,
            'productVersionId' => Defaults::LIVE_VERSION,
            'mediaId' => $mediaId,
            'position' => 2,
        ]], $this->context);
    }

    public function testProductDeleteCascadesProductDocuments(): void
    {
        $productId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $documentId = Uuid::randomHex();

        $this->createMedia($mediaId);
        $this->createProduct($productId, [
            'productDocuments' => [[
                'id' => $documentId,
                'mediaId' => $mediaId,
                'position' => 1,
            ]],
        ]);

        static::assertSame(1, $this->countProductDocuments($documentId));

        $this->connection->executeStatement('
            DELETE FROM `product`
            WHERE `id` = :id
              AND `version_id` = :versionId
        ', [
            'id' => Uuid::fromHexToBytes($productId),
            'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ]);

        static::assertSame(0, $this->countProductDocuments($documentId));
    }

    public function testReferencedMediaDeleteIsRestricted(): void
    {
        $productId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();

        $this->createMedia($mediaId);
        $this->createProduct($productId, [
            'productDocuments' => [[
                'id' => Uuid::randomHex(),
                'mediaId' => $mediaId,
                'position' => 1,
            ]],
        ]);

        static::expectException(RestrictDeleteViolationException::class);

        $this->mediaRepository->delete([['id' => $mediaId]], $this->context);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createProduct(string $productId, array $overrides = []): void
    {
        $this->productRepository->create([
            array_replace_recursive([
                'id' => $productId,
                'name' => 'Test product',
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                ],
                'tax' => ['name' => 'test tax', 'taxRate' => 15],
            ], $overrides),
        ], $this->context);
    }

    private function createMedia(string $mediaId): void
    {
        $this->mediaRepository->create([[
            'id' => $mediaId,
            'private' => true,
            'fileName' => 'manual-' . $mediaId,
            'fileExtension' => 'pdf',
            'mimeType' => 'application/pdf',
        ]], $this->context);
    }

    private function countProductDocuments(string $documentId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `product_document` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($documentId)],
        );
    }
}
