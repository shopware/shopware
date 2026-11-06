<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Document;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
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

        $archive = $this->archiver([$this->document('Manual', $manual), $this->document('Data sheet', $sheet)], [$manual, $sheet])
            ->archive($this->product('Cordless drill'), $this->context);

        static::assertNotNull($archive);
        static::assertSame('Cordless drill.zip', $archive->fileName);
        static::assertStringStartsWith(ProductDocumentArchiver::DIRECTORY . '/' . $this->productId . '/', $archive->path);
        static::assertSame($this->privateFilesystem->fileSize($archive->path), $archive->size);
        static::assertSame([
            ['name' => 'Manual.pdf', 'content' => $manualContent, 'stored' => true],
            ['name' => 'Data sheet.pdf', 'content' => 'sheet content', 'stored' => true],
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

                    return new ProductDocumentCollection([$this->document('Manual', $manual)]);
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

    public function testNamesTheFilesAfterTheDocumentTitles(): void
    {
        $manual = $this->file('manual', 'manual content');
        $translation = $this->file('manual-de', 'translated manual');
        $notes = $this->file('safety-notes', 'safety content');
        $warranty = $this->file('warranty', 'warranty content');

        $archive = $this->archiver([
            $this->document('Manual', $manual),
            $this->document('manual', $translation),
            $this->document('Safety: A/B', $notes),
            $this->document('', $warranty),
        ], [$manual, $translation, $notes, $warranty])->archive($this->product('Cordless drill'), $this->context);

        static::assertNotNull($archive);
        static::assertSame(
            ['Manual.pdf', 'manual (2).pdf', 'Safety- A-B.pdf', 'warranty.pdf'],
            array_column($this->entries($archive), 'name')
        );
    }

    public function testReusesTheStoredArchiveUntilTheDocumentsChange(): void
    {
        $manual = $this->file('manual', 'manual content');
        $media = new MediaCollection([$manual]);
        $archiver = $this->archiver(
            [$this->document('Manual', $manual), $this->document('Manual', $manual), $this->document('Handbook', $manual)],
            [$manual],
            [[0], [1], [2]],
            [$media, $media, $media],
        );

        $first = $archiver->archive($this->product('Cordless drill'), $this->context);
        $this->publicFilesystem->delete($manual->getPath());

        $second = $archiver->archive($this->product('Cordless drill'), $this->context);

        static::assertNotNull($first);
        static::assertNotNull($second);
        static::assertSame($first->path, $second->path);

        $this->publicFilesystem->write($manual->getPath(), 'manual content');
        $renamed = $archiver->archive($this->product('Cordless drill'), $this->context);

        static::assertNotNull($renamed);
        static::assertNotSame($first->path, $renamed->path);
        static::assertSame(['Handbook.pdf'], array_column($this->entries($renamed), 'name'));
    }

    public function testStoresTheArchiveWithTheProductTheDocumentsBelongTo(): void
    {
        $manual = $this->file('manual', 'manual content');
        $parentId = Uuid::randomHex();

        $archive = $this->archiver([$this->document('Manual', $manual, $parentId)], [$manual])
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

    private function document(string $title, MediaEntity $file, ?string $productId = null): ProductDocumentEntity
    {
        $document = new ProductDocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setProductId($productId ?? $this->productId);
        $document->setTranslated(['title' => $title, 'mediaId' => $file->getId()]);

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
