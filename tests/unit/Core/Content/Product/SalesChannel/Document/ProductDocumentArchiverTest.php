<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Document;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToMoveFile;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\SalesChannel\Document\ProductDocumentArchive;
use Shopware\Core\Content\Product\SalesChannel\Document\ProductDocumentArchiver;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Filesystem\Filesystem as LocalFilesystem;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentArchiver::class)]
class ProductDocumentArchiverTest extends TestCase
{
    private Filesystem $publicFilesystem;

    private Filesystem $privateFilesystem;

    private SalesChannelContext $context;

    private string $productId;

    protected function setUp(): void
    {
        $this->publicFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->privateFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->context = Generator::generateSalesChannelContext();
        $this->productId = Uuid::randomHex();
    }

    public function testStoresTheDocumentsInTheirOrderWithoutCompression(): void
    {
        // content that compresses well, which a compressed entry would shrink
        $manualContent = str_repeat('manual content ', 100);
        $manual = $this->createFile('manual', $manualContent);
        $sheet = $this->createFile('data-sheet', 'sheet content');

        $archive = $this->createArchiver([$this->createDocument($manual), $this->createDocument($sheet)], [$manual, $sheet])
            ->archive($this->createProduct('Cordless drill'), $this->context);

        static::assertNotNull($archive);
        static::assertSame('Cordless drill.zip', $archive->fileName);
        static::assertStringStartsWith(ProductDocumentArchiver::DIRECTORY . '/' . $this->productId . '/', $archive->path);
        static::assertSame($this->privateFilesystem->fileSize($archive->path), $archive->size);
        static::assertSame([
            ['name' => 'manual.pdf', 'content' => $manualContent, 'stored' => true],
            ['name' => 'data-sheet.pdf', 'content' => 'sheet content', 'stored' => true],
        ], $this->readEntries($archive));
    }

    public function testReadsTheStoredArchive(): void
    {
        $manual = $this->createFile('manual', 'manual content');
        $archiver = $this->createArchiver([$this->createDocument($manual)], [$manual]);

        $archive = $archiver->archive($this->createProduct('Cordless drill'), $this->context);

        static::assertNotNull($archive);
        static::assertSame($this->privateFilesystem->read($archive->path), $archiver->readStream($archive)->getContents());
    }

    public function testLoadsTheDocumentsTheProductPageShows(): void
    {
        $manual = $this->createFile('manual', 'manual content');
        $sheet = $this->createFile('data-sheet', 'sheet content');

        $archiver = new ProductDocumentArchiver(
            new StaticEntityRepository([
                function (Criteria $criteria) use ($manual, $sheet): ProductDocumentCollection {
                    static::assertEquals([new EqualsFilter('product.id', $this->productId)], $criteria->getFilters());
                    static::assertEquals([new FieldSorting('position')], $criteria->getSorting());

                    return new ProductDocumentCollection([$this->createDocument($manual), $this->createDocument($sheet), $this->createDocument($manual)]);
                },
            ]),
            new StaticEntityRepository([
                static function (Criteria $criteria) use ($manual, $sheet): MediaCollection {
                    static::assertSame([$manual->getId(), $sheet->getId()], $criteria->getIds());
                    static::assertSame([], $criteria->getSorting());
                    static::assertEquals([
                        new EqualsFilter('private', false),
                        new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('path', null)]),
                    ], $criteria->getFilters());

                    return new MediaCollection();
                },
            ]),
            $this->publicFilesystem,
            $this->privateFilesystem,
            new LocalFilesystem(),
            new Psr17Factory(),
            new LockFactory(new InMemoryStore()),
        );

        static::assertNull($archiver->archive($this->createProduct('Cordless drill'), $this->context));
    }

    public function testAProductWithoutDocumentsHasNoArchive(): void
    {
        static::assertNull($this->createArchiver([], [])->archive($this->createProduct('Cordless drill'), $this->context));
    }

    public function testNamesTheEntriesAfterTheirFiles(): void
    {
        $manual = $this->createFile('manual', 'manual content');
        $translation = $this->createFile('MANUAL', 'translated manual');

        $unnamed = new MediaEntity();
        $unnamed->setId(Uuid::randomHex());
        $unnamed->setFileExtension('pdf');
        $unnamed->setPath('media/unnamed.pdf');
        $this->publicFilesystem->write($unnamed->getPath(), 'unnamed content');

        $archive = $this->createArchiver(
            [$this->createDocument($manual), $this->createDocument($translation), $this->createDocument($unnamed)],
            [$manual, $translation, $unnamed],
        )->archive($this->createProduct('Cordless drill'), $this->context);

        static::assertNotNull($archive);
        static::assertSame(
            ['manual.pdf', 'MANUAL (2).pdf', $unnamed->getId() . '.pdf'],
            array_column($this->readEntries($archive), 'name')
        );
    }

    public function testKeepsEntriesInsideTheArchiveFolder(): void
    {
        // names the API can save, unlike uploads
        $parents = $this->createFile('parents', 'parents content');
        $parents->setFileName('../../outside/payload');
        $backslashes = $this->createFile('backslashes', 'backslashes content');
        $backslashes->setFileName('..\\..\\outside\\other');
        $dots = $this->createFile('dots', 'dots content');
        $dots->setFileName('..');
        $dots->setFileExtension('');
        $extension = $this->createFile('extension', 'extension content');
        $extension->setFileExtension('pdf/../../x');

        $archive = $this->createArchiver(
            [$this->createDocument($parents), $this->createDocument($backslashes), $this->createDocument($dots), $this->createDocument($extension)],
            [$parents, $backslashes, $dots, $extension],
        )->archive($this->createProduct('Cordless drill'), $this->context);

        static::assertNotNull($archive);
        static::assertSame(
            ['..-..-outside-payload.pdf', '..-..-outside-other.pdf', $dots->getId(), 'extension.pdf-..-..-x'],
            array_column($this->readEntries($archive), 'name')
        );
    }

    public function testAddsAFileOnceWhenSeveralDocumentsUseIt(): void
    {
        $manual = $this->createFile('manual', 'manual content');

        $archive = $this->createArchiver([$this->createDocument($manual), $this->createDocument($manual)], [$manual])
            ->archive($this->createProduct('Cordless drill'), $this->context);

        static::assertNotNull($archive);
        static::assertSame(['manual.pdf'], array_column($this->readEntries($archive), 'name'));
    }

    #[DataProvider('productNames')]
    public function testNamesTheArchiveAfterTheProduct(string $productName, string $fileName): void
    {
        $manual = $this->createFile('manual', 'manual content');

        $archive = $this->createArchiver([$this->createDocument($manual)], [$manual])
            ->archive($this->createProduct($productName), $this->context);

        static::assertNotNull($archive);
        static::assertSame($fileName, $archive->fileName);
    }

    public static function productNames(): \Generator
    {
        yield 'slashes, which a download name cannot contain, are replaced' => ['Drill 18V/2Ah \\ red', 'Drill 18V-2Ah - red.zip'];
        yield 'invisible characters are removed' => ["Cordless\u{200B} drill\u{202E}", 'Cordless drill.zip'];
        yield 'a product without a name is named after its product number' => ['', 'SW-1000.zip'];
    }

    public function testReusesTheStoredArchiveUntilTheFilesChange(): void
    {
        $manual = $this->createFile('manual', 'manual content');
        $handbook = $this->createFile('handbook', 'manual content');
        $archiver = $this->createArchiver(
            [$this->createDocument($manual), $this->createDocument($handbook)],
            [$manual, $handbook],
            [[0], [0], [1]],
            [new MediaCollection([$manual]), new MediaCollection([$manual]), new MediaCollection([$handbook])],
        );

        $first = $archiver->archive($this->createProduct('Cordless drill'), $this->context);
        $this->publicFilesystem->delete($manual->getPath());

        $second = $archiver->archive($this->createProduct('Cordless drill'), $this->context);

        static::assertNotNull($first);
        static::assertNotNull($second);
        static::assertSame($first->path, $second->path);

        $replaced = $archiver->archive($this->createProduct('Cordless drill'), $this->context);

        static::assertNotNull($replaced);
        static::assertNotSame($first->path, $replaced->path);
        static::assertSame(['handbook.pdf'], array_column($this->readEntries($replaced), 'name'));
    }

    public function testStoresTheArchiveWithTheProductTheDocumentsBelongTo(): void
    {
        $manual = $this->createFile('manual', 'manual content');
        $parentId = Uuid::randomHex();

        $archive = $this->createArchiver([$this->createDocument($manual, $parentId)], [$manual])
            ->archive($this->createProduct('Cordless drill'), $this->context);

        static::assertNotNull($archive);
        static::assertStringStartsWith(ProductDocumentArchiver::DIRECTORY . '/' . $parentId . '/' . $this->context->getLanguageId() . '/', $archive->path);
    }

    public function testReplacesOnlyTheOlderArchiveOfTheSameLanguage(): void
    {
        $manual = $this->createFile('manual', 'manual content');
        $handbook = $this->createFile('handbook', 'handbook content');
        $german = Generator::generateSalesChannelContext(baseContext: new Context(new SystemSource(), [], Defaults::CURRENCY, [Uuid::randomHex()]));
        $archiver = $this->createArchiver(
            [$this->createDocument($manual), $this->createDocument($handbook)],
            [$manual, $handbook],
            [[0], [0], [1]],
            [new MediaCollection([$manual]), new MediaCollection([$manual]), new MediaCollection([$handbook])],
        );

        $older = $archiver->archive($this->createProduct('Cordless drill'), $this->context);
        $germanArchive = $archiver->archive($this->createProduct('Cordless drill'), $german);
        static::assertNotNull($older);
        static::assertNotNull($germanArchive);
        // another request's upload in progress
        $upload = $older->path . '.' . Uuid::randomHex();
        $this->privateFilesystem->write($upload, 'zip');

        $newer = $archiver->archive($this->createProduct('Cordless drill'), $this->context);

        static::assertNotNull($newer);
        static::assertFalse($this->privateFilesystem->fileExists($older->path));
        static::assertTrue($this->privateFilesystem->fileExists($newer->path));
        static::assertTrue($this->privateFilesystem->fileExists($germanArchive->path));
        static::assertTrue($this->privateFilesystem->fileExists($upload));
    }

    public function testReusesTheArchiveAnotherRequestBuiltWhileItWaited(): void
    {
        $manual = $this->createFile('manual', 'manual content');
        $path = $this->createArchiver([$this->createDocument($manual)], [$manual])
            ->archive($this->createProduct('Cordless drill'), $this->context)?->path;
        static::assertIsString($path);
        $this->privateFilesystem->delete($path);

        // the other request stores the archive while this one waits for the lock
        $lockStore = new class(fn () => $this->privateFilesystem->write($path, 'built by another request')) implements PersistingStoreInterface {
            public function __construct(private readonly \Closure $onWait)
            {
            }

            public function save(Key $key): void
            {
                ($this->onWait)();
            }

            public function delete(Key $key): void
            {
            }

            public function exists(Key $key): bool
            {
                return false;
            }

            public function putOffExpiration(Key $key, float $ttl): void
            {
            }
        };

        $archive = $this->createArchiver([$this->createDocument($manual)], [$manual], lockFactory: new LockFactory($lockStore))
            ->archive($this->createProduct('Cordless drill'), $this->context);

        static::assertSame($path, $archive?->path);
        static::assertSame('built by another request', $this->privateFilesystem->read($path));
    }

    public function testRemovesTheTemporaryFileWhenItCannotBeMovedIntoPlace(): void
    {
        $manual = $this->createFile('manual', 'manual content');
        $failure = UnableToMoveFile::fromLocationTo('temporary', 'final');
        $temporaryPath = null;

        $privateFilesystem = $this->createMock(FilesystemOperator::class);
        $privateFilesystem->method('fileExists')->willReturn(false);
        $privateFilesystem->expects($this->once())
            ->method('writeStream')
            ->willReturnCallback(static function (string $location) use (&$temporaryPath): void {
                $temporaryPath = $location;
            });
        $privateFilesystem->expects($this->once())
            ->method('move')
            ->willReturnCallback(static function (string $source, string $destination) use (&$temporaryPath, $failure): void {
                static::assertSame($temporaryPath, $source);
                static::assertNotSame($destination, $source);

                throw $failure;
            });
        $privateFilesystem->expects($this->once())
            ->method('delete')
            ->willReturnCallback(static function (string $location) use (&$temporaryPath): void {
                static::assertSame($temporaryPath, $location);
            });

        $this->expectExceptionObject($failure);

        $this->createArchiver([$this->createDocument($manual)], [$manual], privateFilesystem: $privateFilesystem)
            ->archive($this->createProduct('Cordless drill'), $this->context);
    }

    /**
     * @param list<ProductDocumentEntity> $documents
     * @param list<MediaEntity> $files
     * @param list<list<int>>|null $documentsPerCall
     * @param list<MediaCollection>|null $mediaPerCall
     */
    private function createArchiver(
        array $documents,
        array $files,
        ?array $documentsPerCall = null,
        ?array $mediaPerCall = null,
        ?FilesystemOperator $privateFilesystem = null,
        ?LockFactory $lockFactory = null,
    ): ProductDocumentArchiver {
        $documentsPerCall ??= [array_keys($documents)];
        $mediaPerCall ??= [new MediaCollection($files)];

        return new ProductDocumentArchiver(
            new StaticEntityRepository(array_map(
                static fn (array $indexes): ProductDocumentCollection => new ProductDocumentCollection(array_map(static fn (int $index) => $documents[$index], $indexes)),
                $documentsPerCall,
            )),
            new StaticEntityRepository($mediaPerCall),
            $this->publicFilesystem,
            $privateFilesystem ?? $this->privateFilesystem,
            new LocalFilesystem(),
            new Psr17Factory(),
            $lockFactory ?? new LockFactory(new InMemoryStore()),
        );
    }

    private function createProduct(string $name): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId($this->productId);
        $product->setProductNumber('SW-1000');
        $product->setTranslated(['name' => $name]);

        return $product;
    }

    private function createDocument(MediaEntity $file, ?string $productId = null): ProductDocumentEntity
    {
        $document = new ProductDocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setProductId($productId ?? $this->productId);
        $document->setTranslated(['mediaId' => $file->getId()]);

        return $document;
    }

    private function createFile(string $name, string $content): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setFileName($name);
        $media->setFileExtension('pdf');
        $media->setMimeType('application/pdf');
        $media->setFileSize(\strlen($content));
        $media->setPath('media/' . $name . '.pdf');
        $media->setUploadedAt(new \DateTimeImmutable('2026-11-06 10:00:00'));
        $this->publicFilesystem->write($media->getPath(), $content);

        return $media;
    }

    /**
     * @return list<array{name: string, content: string, stored: bool}>
     */
    private function readEntries(ProductDocumentArchive $archive): array
    {
        $localFilesystem = new LocalFilesystem();
        $path = $localFilesystem->tempnam(sys_get_temp_dir(), 'archive-test-', '.zip');
        $localFilesystem->dumpFile($path, $this->privateFilesystem->read($archive->path));

        $zip = new \ZipArchive();
        static::assertTrue($zip->open($path));

        $entries = [];
        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $stat = $zip->statIndex($index);
            static::assertIsArray($stat);
            $entries[] = [
                'name' => $stat['name'],
                'content' => (string) $zip->getFromIndex($index),
                'stored' => $stat['comp_method'] === \ZipArchive::CM_STORE,
            ];
        }

        $zip->close();
        $localFilesystem->remove($path);

        return $entries;
    }
}
