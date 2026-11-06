<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Document;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Filesystem\Filesystem as LocalFilesystem;

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
        $manual = $this->file('manual', $manualContent);
        $sheet = $this->file('data-sheet', 'sheet content');

        // the media search returns the files in its own order
        $archive = $this->archiver([$this->document($manual), $this->document($sheet)], [$sheet, $manual])
            ->archive($this->product('Cordless drill'), $this->context);

        static::assertNotNull($archive);
        static::assertSame('Cordless drill.zip', $archive->fileName);
        static::assertStringStartsWith(ProductDocumentArchiver::DIRECTORY . '/' . $this->productId . '/', $archive->path);
        static::assertSame($this->privateFilesystem->fileSize($archive->path), $archive->size);
        static::assertSame([
            ['name' => 'manual.pdf', 'content' => $manualContent, 'stored' => true],
            ['name' => 'data-sheet.pdf', 'content' => 'sheet content', 'stored' => true],
        ], $this->entries($archive));
    }

    public function testLoadsTheDocumentsTheProductPageShows(): void
    {
        $manual = $this->file('manual', 'manual content');

        $archiver = new ProductDocumentArchiver(
            new StaticEntityRepository([
                function (Criteria $criteria) use ($manual): ProductDocumentCollection {
                    static::assertEquals([new EqualsFilter('product.id', $this->productId)], $criteria->getFilters());
                    static::assertEquals([new FieldSorting('position')], $criteria->getSorting());

                    return new ProductDocumentCollection([$this->document($manual)]);
                },
            ]),
            new StaticEntityRepository([
                static function (Criteria $criteria) use ($manual): MediaCollection {
                    static::assertSame([$manual->getId()], $criteria->getIds());
                    static::assertEquals([new EqualsFilter('private', false)], $criteria->getFilters());

                    return new MediaCollection();
                },
            ]),
            $this->publicFilesystem,
            $this->privateFilesystem,
            new LocalFilesystem(),
        );

        static::assertNull($archiver->archive($this->product('Cordless drill'), $this->context));
    }

    public function testNamesTheEntriesAfterTheirFiles(): void
    {
        $manual = $this->file('manual', 'manual content');
        $translation = $this->file('MANUAL', 'translated manual');

        $unnamed = new MediaEntity();
        $unnamed->setId(Uuid::randomHex());
        $unnamed->setFileExtension('pdf');
        $unnamed->setPath('media/unnamed.pdf');
        $this->publicFilesystem->write($unnamed->getPath(), 'unnamed content');

        $archive = $this->archiver(
            [$this->document($manual), $this->document($translation), $this->document($unnamed)],
            [$manual, $translation, $unnamed],
        )->archive($this->product('Cordless drill'), $this->context);

        static::assertNotNull($archive);
        static::assertSame(
            ['manual.pdf', 'MANUAL (2).pdf', $unnamed->getId() . '.pdf'],
            array_column($this->entries($archive), 'name')
        );
    }

    public function testLeavesOutMediaWithoutAFile(): void
    {
        $manual = $this->file('manual', 'manual content');
        $empty = new MediaEntity();
        $empty->setId(Uuid::randomHex());

        $archive = $this->archiver([$this->document($empty), $this->document($manual)], [$empty, $manual])
            ->archive($this->product('Cordless drill'), $this->context);

        static::assertNotNull($archive);
        static::assertSame(['manual.pdf'], array_column($this->entries($archive), 'name'));
    }

    public function testAddsAFileOnceWhenSeveralDocumentsUseIt(): void
    {
        $manual = $this->file('manual', 'manual content');

        $archive = $this->archiver([$this->document($manual), $this->document($manual)], [$manual])
            ->archive($this->product('Cordless drill'), $this->context);

        static::assertNotNull($archive);
        static::assertSame(['manual.pdf'], array_column($this->entries($archive), 'name'));
    }

    #[DataProvider('productNames')]
    public function testNamesTheArchiveAfterTheProduct(string $productName, string $fileName): void
    {
        $manual = $this->file('manual', 'manual content');

        $archive = $this->archiver([$this->document($manual)], [$manual])
            ->archive($this->product($productName), $this->context);

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
        $manual = $this->file('manual', 'manual content');
        $handbook = $this->file('handbook', 'manual content');
        $archiver = $this->archiver(
            [$this->document($manual), $this->document($handbook)],
            [$manual, $handbook],
            [[0], [0], [1]],
            [new MediaCollection([$manual]), new MediaCollection([$manual]), new MediaCollection([$handbook])],
        );

        $first = $archiver->archive($this->product('Cordless drill'), $this->context);
        $this->publicFilesystem->delete($manual->getPath());

        $second = $archiver->archive($this->product('Cordless drill'), $this->context);

        static::assertNotNull($first);
        static::assertNotNull($second);
        static::assertSame($first->path, $second->path);

        $replaced = $archiver->archive($this->product('Cordless drill'), $this->context);

        static::assertNotNull($replaced);
        static::assertNotSame($first->path, $replaced->path);
        static::assertSame(['handbook.pdf'], array_column($this->entries($replaced), 'name'));
    }

    public function testStoresTheArchiveWithTheProductTheDocumentsBelongTo(): void
    {
        $manual = $this->file('manual', 'manual content');
        $parentId = Uuid::randomHex();

        $archive = $this->archiver([$this->document($manual, $parentId)], [$manual])
            ->archive($this->product('Cordless drill'), $this->context);

        static::assertNotNull($archive);
        static::assertStringStartsWith(ProductDocumentArchiver::DIRECTORY . '/' . $parentId . '/', $archive->path);
    }

    public function testTagsNameTheProductTheirArchivesBelongTo(): void
    {
        static::assertSame($this->productId, ProductDocumentArchiver::productIdOfTag(ProductDocumentArchiver::buildTag($this->productId)));
        static::assertNull(ProductDocumentArchiver::productIdOfTag('product-' . $this->productId));
        static::assertNull(ProductDocumentArchiver::productIdOfTag(ProductDocumentArchiver::buildTag('../media')));
    }

    /**
     * @param list<ProductDocumentEntity> $documents
     * @param list<MediaEntity> $files
     * @param list<list<int>>|null $documentsPerCall the documents each call finds, by index
     * @param list<MediaCollection>|null $mediaPerCall
     */
    private function archiver(array $documents, array $files, ?array $documentsPerCall = null, ?array $mediaPerCall = null): ProductDocumentArchiver
    {
        $documentsPerCall ??= [array_keys($documents)];
        $mediaPerCall ??= [new MediaCollection($files)];

        return new ProductDocumentArchiver(
            new StaticEntityRepository(array_map(
                static fn (array $indexes): ProductDocumentCollection => new ProductDocumentCollection(array_map(static fn (int $index) => $documents[$index], $indexes)),
                $documentsPerCall,
            )),
            new StaticEntityRepository($mediaPerCall),
            $this->publicFilesystem,
            $this->privateFilesystem,
            new LocalFilesystem(),
        );
    }

    private function product(string $name): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId($this->productId);
        $product->setProductNumber('SW-1000');
        $product->setTranslated(['name' => $name]);

        return $product;
    }

    private function document(MediaEntity $file, ?string $productId = null): ProductDocumentEntity
    {
        $document = new ProductDocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setProductId($productId ?? $this->productId);
        $document->setTranslated(['mediaId' => $file->getId()]);

        return $document;
    }

    private function file(string $name, string $content): MediaEntity
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
    private function entries(ProductDocumentArchive $archive): array
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
