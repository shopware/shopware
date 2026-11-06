<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataException;
use Shopware\Core\Framework\Demodata\Generator\ProductDocumentGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityWriter;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentGenerator::class)]
class ProductDocumentGeneratorTest extends TestCase
{
    private StaticEntityWriter $writer;

    /**
     * @var list<MediaFile>
     */
    private array $persistedFiles = [];

    /**
     * @var list<string>
     */
    private array $writtenContents = [];

    /**
     * @var array<string>
     */
    private array $inheritanceUpdatedIds = [];

    protected function setUp(): void
    {
        $this->writer = new StaticEntityWriter();
    }

    public function testGetDefinition(): void
    {
        static::assertSame(ProductDocumentDefinition::class, $this->createGenerator([], [], false)->getDefinition());
    }

    public function testGenerateWithoutProductsThrows(): void
    {
        $this->expectExceptionObject(DemodataException::wrongExecutionOrder());

        $this->createGenerator([], [], false)->generate(1, $this->createContext());
    }

    public function testGenerateUpdatesTheDocumentInheritanceOfTheProductsAndTheirVariants(): void
    {
        $product = $this->product('SW1');
        $variantId = Uuid::randomHex();

        $this->createGenerator([$product], [], false, [$variantId])->generate(1, $this->createContext());

        static::assertSame([$product['id'], $variantId], $this->inheritanceUpdatedIds);
    }

    public function testGenerateAttachesTheRequestedNumberOfDocumentsToProducts(): void
    {
        $products = [$this->product('SW1'), $this->product('SW2'), $this->product('SW3'), $this->product('SW4'), $this->product('SW5')];
        $folderId = Uuid::randomHex();

        $this->createGenerator($products, [], $folderId)->generate(5, $this->createContext());

        static::assertCount(5, $this->writer->upserts);

        $productIds = array_column($products, 'id');
        $positionsByProduct = [];
        foreach ($this->writer->upserts as $document) {
            static::assertContains($document['productId'], $productIds);
            $positionsByProduct[$document['productId']][] = $document['position'];

            static::assertSame([Defaults::LANGUAGE_SYSTEM], array_keys($document['translations']));
            $translation = $document['translations'][Defaults::LANGUAGE_SYSTEM];
            static::assertNotEmpty($translation['title']);
            static::assertTrue($translation['customFields']['shopwareDemoData']);
            static::assertContains($translation['mediaId'], array_column($this->writer->inserts, 'id'));
        }

        foreach ($positionsByProduct as $positions) {
            static::assertSame(range(0, \count($positions) - 1), $positions);
        }

        static::assertCount(5, $this->writer->inserts);
        foreach ($this->writer->inserts as $media) {
            static::assertSame($folderId, $media['mediaFolderId']);
            static::assertFalse($media['private']);
            static::assertTrue($media['customFields']['shopwareDemoData']);
        }
    }

    public function testGenerateStoresAPdfForEveryFile(): void
    {
        $this->createGenerator([$this->product('SW1')], [], false)->generate(1, $this->createContext());

        static::assertCount(1, $this->persistedFiles);
        static::assertSame('application/pdf', $this->persistedFiles[0]->getMimeType());
        static::assertSame('pdf', $this->persistedFiles[0]->getFileExtension());

        static::assertCount(1, $this->writtenContents);
        $content = $this->writtenContents[0];
        static::assertStringStartsWith('%PDF-', $content);
        static::assertSame(\strlen($content), $this->persistedFiles[0]->getFileSize());

        static::assertNull($this->writer->inserts[0]['mediaFolderId']);
    }

    public function testGenerateGivesOtherLanguagesTheirOwnFileOrLetsThemFallBack(): void
    {
        $germanId = Uuid::randomHex();

        $this->createGenerator([$this->product('SW1'), $this->product('SW2'), $this->product('SW3'), $this->product('SW4')], [$germanId => 'de-DE'], false)
            ->generate(16, $this->createContext());

        $germanTitles = ['Bedienungsanleitung', 'Technisches Datenblatt', 'Sicherheitshinweise', 'Konformitätserklärung', 'Garantiebedingungen', 'Installationsanleitung'];

        $translatedDocuments = 0;
        foreach ($this->writer->upserts as $document) {
            if (!isset($document['translations'][$germanId])) {
                continue;
            }

            ++$translatedDocuments;
            $translation = $document['translations'][$germanId];
            static::assertContains($translation['title'], $germanTitles);
            static::assertNotSame($document['translations'][Defaults::LANGUAGE_SYSTEM]['mediaId'], $translation['mediaId']);
        }

        static::assertGreaterThan(0, $translatedDocuments);
        static::assertLessThan(\count($this->writer->upserts), $translatedDocuments);
        static::assertCount(\count($this->writer->upserts) + $translatedDocuments, $this->writer->inserts);
    }

    /**
     * @return array{id: string, productNumber: string, name: string}
     */
    private function product(string $productNumber): array
    {
        return ['id' => Uuid::randomHex(), 'productNumber' => $productNumber, 'name' => 'Product ' . $productNumber];
    }

    /**
     * @param list<array{id: string, productNumber: string, name: string}> $products
     * @param array<string, string> $languages
     * @param list<string> $variantIds
     */
    private function createGenerator(array $products, array $languages, string|false $folderId, array $variantIds = []): ProductDocumentGenerator
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($products);
        $connection->method('fetchAllKeyValue')->willReturn($languages);
        $connection->method('fetchOne')->willReturn($folderId);
        $connection->method('fetchFirstColumn')->willReturn($variantIds);

        $inheritanceUpdater = static::createStub(InheritanceUpdater::class);
        $inheritanceUpdater->method('update')->willReturnCallback(function (string $entity, array $ids): void {
            static::assertSame(ProductDefinition::ENTITY_NAME, $entity);
            $this->inheritanceUpdatedIds = $ids;
        });

        $fileSaver = static::createStub(FileSaver::class);
        $fileSaver->method('persistFileToMedia')->willReturnCallback(function (MediaFile $file): void {
            $this->persistedFiles[] = $file;
        });

        $fileNameProvider = static::createStub(FileNameProvider::class);
        $fileNameProvider->method('provide')->willReturnArgument(0);

        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('tempnam')->willReturn('/tmp/demodata-document');
        $filesystem->method('dumpFile')->willReturnCallback(function (string $path, string $content): void {
            $this->writtenContents[] = $content;
        });

        return new ProductDocumentGenerator(
            $this->writer,
            new ProductDocumentDefinition(),
            new MediaDefinition(),
            $fileSaver,
            $fileNameProvider,
            $filesystem,
            $inheritanceUpdater,
            $connection,
        );
    }

    private function createContext(): DemodataContext
    {
        $faker = Factory::create();
        $faker->seed(1);

        return new DemodataContext(
            Context::createDefaultContext(),
            $faker,
            __DIR__,
            static::createStub(SymfonyStyle::class),
            static::createStub(DefinitionInstanceRegistry::class),
        );
    }
}
