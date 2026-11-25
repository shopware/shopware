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
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\Generator\ProductDocumentFileProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityWriter;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentFileProvider::class)]
class ProductDocumentFileProviderTest extends TestCase
{
    private StaticEntityWriter $writer;

    /**
     * @var list<MediaFile>
     */
    private array $persistedFiles = [];

    /**
     * @var list<string>
     */
    private array $fileNames = [];

    /**
     * @var list<string>
     */
    private array $writtenContents = [];

    protected function setUp(): void
    {
        $this->writer = new StaticEntityWriter();
    }

    public function testCreatesOneFileForEveryDocumentType(): void
    {
        $folderId = Uuid::randomHex();

        $documents = $this->createProvider([], $folderId)->getDocuments($this->createContext());

        static::assertCount(10, $documents);
        static::assertCount(10, $this->writer->inserts);
        static::assertCount(10, $this->persistedFiles);
        static::assertSame(array_column($this->writer->inserts, 'id'), array_column($documents, 'mediaId'));

        static::assertSame('User manual', $documents[0]['title']);
        static::assertSame('demo-user-manual', $this->fileNames[0]);

        foreach ($this->writer->inserts as $media) {
            static::assertSame($folderId, $media['mediaFolderId']);
            static::assertFalse($media['private']);
            static::assertTrue($media['customFields']['shopwareDemoData']);
        }
    }

    public function testStoresAPdfForEveryFile(): void
    {
        $this->createProvider([], false)->getDocuments($this->createContext());

        static::assertNull($this->writer->inserts[0]['mediaFolderId']);

        $file = $this->persistedFiles[0];
        static::assertSame('application/pdf', $file->getMimeType());
        static::assertSame('pdf', $file->getFileExtension());
        static::assertStringStartsWith('%PDF-', $this->writtenContents[0]);
        static::assertSame(\strlen($this->writtenContents[0]), $file->getFileSize());
    }

    public function testReusesTheFilesOfEarlierRuns(): void
    {
        $userManualId = Uuid::randomHex();

        $documents = $this->createProvider(['demo-user-manual' => $userManualId], false)
            ->getDocuments($this->createContext());

        static::assertSame($userManualId, $documents[0]['mediaId']);
        static::assertCount(9, $this->writer->inserts);
        static::assertNotContains('demo-user-manual', $this->fileNames);
    }

    /**
     * @param array<string, string> $existingFiles
     */
    private function createProvider(array $existingFiles, string|false $folderId): ProductDocumentFileProvider
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn($existingFiles);
        $connection->method('fetchOne')->willReturn($folderId);

        $fileSaver = static::createStub(FileSaver::class);
        $fileSaver->method('persistFileToMedia')->willReturnCallback(function (MediaFile $file): void {
            $this->persistedFiles[] = $file;
        });

        $fileNameProvider = static::createStub(FileNameProvider::class);
        $fileNameProvider->method('provide')->willReturnCallback(function (string $fileName): string {
            $this->fileNames[] = $fileName;

            return $fileName;
        });

        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('tempnam')->willReturn('/tmp/demodata-document');
        $filesystem->method('dumpFile')->willReturnCallback(function (string $path, string $content): void {
            $this->writtenContents[] = $content;
        });

        return new ProductDocumentFileProvider(
            $this->writer,
            new MediaDefinition(),
            $fileSaver,
            $fileNameProvider,
            $filesystem,
            $connection,
        );
    }

    private function createContext(): DemodataContext
    {
        return new DemodataContext(
            Context::createDefaultContext(),
            Factory::create(),
            __DIR__,
            static::createStub(SymfonyStyle::class),
            static::createStub(DefinitionInstanceRegistry::class),
        );
    }
}
