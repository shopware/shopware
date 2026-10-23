<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Aggregate\ProductDocument;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
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

    public function testDocumentIsWrittenWithNameAndFileAndLoadsItsFileWithoutAskingForIt(): void
    {
        $productId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $documentId = Uuid::randomHex();

        $this->createMedia($mediaId);
        $this->createProduct($productId, [
            'productDocuments' => [[
                'id' => $documentId,
                'name' => 'Datasheet',
                'mediaId' => $mediaId,
                'position' => 5,
            ]],
        ]);

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('productDocuments');

        $productDocument = $this->loadDocument($productId, $documentId, $criteria, $this->context);

        static::assertSame($productId, $productDocument->getProductId());
        static::assertSame(Defaults::LIVE_VERSION, $productDocument->getProductVersionId());
        static::assertSame(5, $productDocument->getPosition());
        static::assertSame('Datasheet', $productDocument->getTranslation('name'));
        static::assertSame($mediaId, $productDocument->getTranslation('mediaId'));
        static::assertSame($mediaId, $productDocument->getMedia()?->getId());
    }

    public function testNameAndFileAreMaintainedPerLanguageAndFallBackToTheDefaultLanguage(): void
    {
        $productId = Uuid::randomHex();
        $englishManual = Uuid::randomHex();
        $germanManual = Uuid::randomHex();
        $englishDatasheet = Uuid::randomHex();
        $manualId = Uuid::randomHex();
        $datasheetId = Uuid::randomHex();
        $germanLanguageId = $this->getDeDeLanguageId();

        $this->createMedia($englishManual, $germanManual, $englishDatasheet);
        $this->createProduct($productId, [
            'productDocuments' => [
                [
                    'id' => $manualId,
                    'position' => 1,
                    'translations' => [
                        Defaults::LANGUAGE_SYSTEM => ['name' => 'Manual', 'mediaId' => $englishManual],
                        $germanLanguageId => ['name' => 'Handbuch', 'mediaId' => $germanManual],
                    ],
                ],
                [
                    'id' => $datasheetId,
                    'position' => 2,
                    'translations' => [
                        Defaults::LANGUAGE_SYSTEM => ['name' => 'Datasheet', 'mediaId' => $englishDatasheet],
                    ],
                ],
            ],
        ]);

        $germanContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$germanLanguageId, Defaults::LANGUAGE_SYSTEM]);

        $manual = $this->loadDocument($productId, $manualId, $this->criteria($productId), $germanContext);
        static::assertSame('Handbuch', $manual->getTranslation('name'));
        static::assertSame($germanManual, $manual->getMedia()?->getId());

        $datasheet = $this->loadDocument($productId, $datasheetId, $this->criteria($productId), $germanContext);
        static::assertSame('Datasheet', $datasheet->getTranslation('name'), 'German falls back to the default language name');
        static::assertSame($englishDatasheet, $datasheet->getMedia()?->getId(), 'German falls back to the default language file');

        $englishManualDocument = $this->loadDocument($productId, $manualId, $this->criteria($productId), $this->context);
        static::assertSame('Manual', $englishManualDocument->getTranslation('name'));
        static::assertSame($englishManual, $englishManualDocument->getMedia()?->getId());
    }

    public function testRequestingTheRuntimeMediaAssociationStillResolvesTheFile(): void
    {
        $productId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $documentId = Uuid::randomHex();

        $this->createMedia($mediaId);
        $this->createProduct($productId, [
            'productDocuments' => [['id' => $documentId, 'name' => 'Manual', 'mediaId' => $mediaId]],
        ]);

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('productDocuments.media');

        static::assertSame($mediaId, $this->loadDocument($productId, $documentId, $criteria, $this->context)->getMedia()?->getId());
    }

    public function testDocumentNeedsANameAndFileInTheDefaultLanguage(): void
    {
        $productId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();

        $this->createMedia($mediaId);
        $this->createProduct($productId);

        static::expectException(WriteException::class);

        $this->productDocumentRepository->create([[
            'id' => Uuid::randomHex(),
            'productId' => $productId,
            'productVersionId' => Defaults::LIVE_VERSION,
            'translations' => [
                $this->getDeDeLanguageId() => ['name' => 'Handbuch', 'mediaId' => $mediaId],
            ],
        ]], $this->context);
    }

    public function testProductDeleteCascadesDocumentsAndTheirTranslations(): void
    {
        $productId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $documentId = Uuid::randomHex();

        $this->createMedia($mediaId);
        $this->createProduct($productId, [
            'productDocuments' => [['id' => $documentId, 'name' => 'Manual', 'mediaId' => $mediaId]],
        ]);

        static::assertSame(1, $this->countRows('product_document', 'id', $documentId));
        static::assertSame(1, $this->countRows('product_document_translation', 'product_document_id', $documentId));

        $this->productRepository->delete([['id' => $productId]], $this->context);

        static::assertSame(0, $this->countRows('product_document', 'id', $documentId));
        static::assertSame(0, $this->countRows('product_document_translation', 'product_document_id', $documentId));
    }

    public function testFileOfAnyLanguageCannotBeDeletedWhileLinked(): void
    {
        $productId = Uuid::randomHex();
        $englishFile = Uuid::randomHex();
        $germanFile = Uuid::randomHex();

        $this->createMedia($englishFile, $germanFile);
        $this->createProduct($productId, [
            'productDocuments' => [[
                'id' => Uuid::randomHex(),
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'Manual', 'mediaId' => $englishFile],
                    $this->getDeDeLanguageId() => ['name' => 'Handbuch', 'mediaId' => $germanFile],
                ],
            ]],
        ]);

        static::expectException(RestrictDeleteViolationException::class);

        $this->mediaRepository->delete([['id' => $germanFile]], $this->context);
    }

    private function criteria(string $productId): Criteria
    {
        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('productDocuments');

        return $criteria;
    }

    private function loadDocument(string $productId, string $documentId, Criteria $criteria, Context $context): ProductDocumentEntity
    {
        $product = $this->productRepository->search($criteria, $context)->getEntities()->get($productId);
        static::assertInstanceOf(ProductEntity::class, $product);

        $productDocument = $product->getProductDocuments()?->get($documentId);
        static::assertInstanceOf(ProductDocumentEntity::class, $productDocument);

        return $productDocument;
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

    private function createMedia(string ...$mediaIds): void
    {
        $this->mediaRepository->create(array_map(static fn (string $mediaId): array => [
            'id' => $mediaId,
            'private' => true,
            'fileName' => 'document-' . $mediaId,
            'fileExtension' => 'pdf',
            'mimeType' => 'application/pdf',
        ], $mediaIds), $this->context);
    }

    private function countRows(string $table, string $column, string $id): int
    {
        return (int) $this->connection->fetchOne(
            \sprintf('SELECT COUNT(*) FROM `%s` WHERE `%s` = :id', $table, $column),
            ['id' => Uuid::fromHexToBytes($id)],
        );
    }
}
