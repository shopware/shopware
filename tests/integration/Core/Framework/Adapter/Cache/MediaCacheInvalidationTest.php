<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;
use Shopware\Core\Content\Media\SalesChannel\MediaRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class MediaCacheInvalidationTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private CacheInvalidationSubscriber $subscriber;

    private CacheInvalidator $invalidator;

    /**
     * @var list<list<string>>
     */
    private array $batches = [];

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->invalidator = static::createStub(CacheInvalidator::class);
        $this->invalidator->method('invalidate')->willReturnCallback(function (array $tags): void {
            /** @var list<string> $tags */
            $this->batches[] = $tags;
        });
        $this->subscriber = new CacheInvalidationSubscriber($this->invalidator, $this->connection, productStreamIndexerEnabled: true, mediaBatchSize: 3);
    }

    public function testSharedMediaInvalidatesProductsAndVariantsAcrossVersions(): void
    {
        $media = $this->createMedia();
        $otherMedia = $this->createMedia();
        $parent = $this->createProduct();
        $variant = $this->createProduct($parent);
        $standalone = $this->createProduct();
        $unrelated = $this->createProduct();
        $this->assignMedia($parent, $media);
        $this->assignMedia($parent, $media);
        $this->assignMedia($variant, $media);
        $this->assignMedia($standalone, $media);
        $this->assignMedia($unrelated, $otherMedia);

        $version = Uuid::randomHex();
        $this->connection->insert('version', ['id' => Uuid::fromHexToBytes($version), 'name' => 'Media invalidation test', 'created_at' => '2026-01-01 00:00:00']);
        $this->connection->insert('product', [
            'id' => Uuid::fromHexToBytes($variant),
            'stock' => 0,
            'version_id' => Uuid::fromHexToBytes($version),
            'parent_id' => Uuid::fromHexToBytes($parent),
            'parent_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ]);

        $this->subscriber->invalidateMedia(new MediaIndexerEvent([$media], Context::createDefaultContext()));

        $expected = array_map(EntityCacheKeyGenerator::buildProductTag(...), [$parent, $variant, $standalone]);
        if (Feature::isActive('CACHE_REWORK')) {
            $expected[] = MediaRoute::buildName($media);
        }
        static::assertEqualsCanonicalizing($expected, array_values(array_unique(array_merge(...$this->batches))));
    }

    #[DataProvider('largeCatalogs')]
    public function testInvalidatesEveryProductAcrossTheBatchBoundary(bool $variants): void
    {
        $media = $this->createMedia();
        $parent = $variants ? $this->createProduct() : null;
        if ($parent !== null) {
            $this->assignMedia($parent, $media);
        }
        $expected = $parent === null ? [] : [EntityCacheKeyGenerator::buildProductTag($parent)];
        $queue = new MultiInsertQueryQueue($this->connection, chunkSize: 1000);
        for ($i = 0; $i < 10; ++$i) {
            $id = Uuid::randomHex();
            $expected[] = EntityCacheKeyGenerator::buildProductTag($id);
            $queue->addInsert('product', [
                'id' => Uuid::fromHexToBytes($id),
                'stock' => 0,
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'parent_id' => $parent === null ? null : Uuid::fromHexToBytes($parent),
                // Exercise NULL -> NULL, NULL -> live and live -> live page boundaries.
                'parent_version_id' => $parent === null || $i < 4 ? null : Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]);
            if (!$variants) {
                $queue->addInsert('product_media', [
                    'id' => Uuid::randomBytes(),
                    'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                    'product_id' => Uuid::fromHexToBytes($id),
                    'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                    'media_id' => Uuid::fromHexToBytes($media),
                    'created_at' => '2026-01-01 00:00:00',
                ]);
            }
        }
        $queue->execute();

        $this->subscriber->invalidateMedia(new MediaIndexerEvent([$media], Context::createDefaultContext()));

        if (Feature::isActive('CACHE_REWORK')) {
            $expected[] = MediaRoute::buildName($media);
        }
        static::assertGreaterThan(1, \count($this->batches));
        foreach ($this->batches as $batch) {
            static::assertLessThanOrEqual(3, \count(array_filter($batch, static fn (string $tag): bool => str_starts_with($tag, 'product-'))));
        }
        $productTags = array_merge(...$this->batches);
        static::assertEqualsCanonicalizing($expected, array_values(array_unique($productTags)));
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function largeCatalogs(): iterable
    {
        yield 'one parent with ten variants' => [true];
        yield 'ten products without variants' => [false];
    }

    public function testMediaWithoutProductsStillInvalidatesItsOwnTag(): void
    {
        $media = $this->createMedia();

        $this->subscriber->invalidateMedia(new MediaIndexerEvent([$media], Context::createDefaultContext()));

        static::assertSame(Feature::isActive('CACHE_REWORK') ? [[MediaRoute::buildName($media)]] : [], $this->batches);
    }

    public function testSmallUpdateCombinesDocumentsProductsAndOtherMediaUsages(): void
    {
        $media = $this->createMedia();
        $product = $this->createProduct();
        $documentOwner = $this->createProduct();
        $this->assignMedia($product, $media);
        $this->assignDocument($product, $media);
        $this->assignDocument($documentOwner, $media);
        $category = Uuid::randomHex();
        static::getContainer()->get('category.repository')->create([
            ['id' => $category, 'name' => 'Media usage', 'mediaId' => $media],
        ], Context::createDefaultContext());

        $this->subscriber->invalidateMedia(new MediaIndexerEvent([$media], Context::createDefaultContext()));

        $expected = [
            EntityCacheKeyGenerator::buildProductTag($product),
            EntityCacheKeyGenerator::buildProductTag($documentOwner),
            CategoryRoute::buildName($category),
        ];
        if (Feature::isActive('CACHE_REWORK')) {
            $expected[] = MediaRoute::buildName($media);
        }
        static::assertCount(1, $this->batches);
        static::assertEqualsCanonicalizing($expected, $this->batches[0]);
    }

    public function testSmallMediaPagesAreBufferedWithListingAndStreamTags(): void
    {
        $category = Uuid::randomHex();
        static::getContainer()->get('category.repository')->create([
            ['id' => $category, 'name' => 'Shared listing'],
        ], Context::createDefaultContext());
        $stream = Uuid::randomHex();
        $this->connection->insert('product_stream', ['id' => Uuid::fromHexToBytes($stream), 'created_at' => '2026-01-01 00:00:00']);
        $mediaIds = $productTags = [];
        for ($i = 0; $i < 5; ++$i) {
            $mediaIds[] = $media = $this->createMedia();
            for ($j = 0; $j < 2; ++$j) {
                $product = $this->createProduct();
                $productTags[] = EntityCacheKeyGenerator::buildProductTag($product);
                $this->assignMedia($product, $media);
                $this->connection->insert('product_category_tree', [
                    'product_id' => Uuid::fromHexToBytes($product),
                    'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                    'category_id' => Uuid::fromHexToBytes($category),
                    'category_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                ]);
                $this->connection->insert('product_stream_mapping', [
                    'product_id' => Uuid::fromHexToBytes($product),
                    'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                    'product_stream_id' => Uuid::fromHexToBytes($stream),
                ]);
            }
        }

        $mediaIds[] = $this->createMedia();

        $this->subscriber->invalidateMedia(new MediaIndexerEvent($mediaIds, Context::createDefaultContext()));

        static::assertCount(4, $this->batches);
        foreach ($this->batches as $i => $batch) {
            static::assertCount($i < 3 ? 3 : 1, array_intersect($batch, $productTags));
            static::assertContains(ProductListingRoute::buildName($category), $batch);
            static::assertContains(EntityCacheKeyGenerator::buildStreamTag($stream), $batch);
        }
        $expected = [...$productTags, ProductListingRoute::buildName($category), EntityCacheKeyGenerator::buildStreamTag($stream)];
        if (Feature::isActive('CACHE_REWORK')) {
            $expected = [...$expected, ...array_map(MediaRoute::buildName(...), $mediaIds)];
        }
        static::assertEqualsCanonicalizing($expected, array_values(array_unique(array_merge(...$this->batches))));
    }

    public function testProbeProductsAndVariantsAreSplitAtTheProductLimit(): void
    {
        $media = $this->createMedia();
        $products = [];
        for ($i = 0; $i < 2; ++$i) {
            $products[] = $parent = $this->createProduct();
            $products[] = $this->createProduct($parent);
            $this->assignMedia($parent, $media);
        }

        $this->subscriber->invalidateMedia(new MediaIndexerEvent([$media], Context::createDefaultContext()));

        $expected = array_map(EntityCacheKeyGenerator::buildProductTag(...), $products);
        static::assertCount(2, $this->batches);
        static::assertCount(3, array_intersect($this->batches[0], $expected));
        static::assertCount(1, array_intersect($this->batches[1], $expected));
        if (Feature::isActive('CACHE_REWORK')) {
            $expected[] = MediaRoute::buildName($media);
        }
        static::assertEqualsCanonicalizing($expected, array_merge(...$this->batches));
    }

    public function testDocumentOwnersAreInvalidatedAcrossPages(): void
    {
        $media = $this->createMedia();
        $expected = [];
        for ($i = 0; $i < 7; ++$i) {
            $owner = $this->createProduct();
            $expected[] = EntityCacheKeyGenerator::buildProductTag($owner);
            $this->assignDocument($owner, $media);
            $this->assignDocument($this->createProduct($owner), $media);
        }

        $this->subscriber->invalidateMedia(new MediaIndexerEvent([$media], Context::createDefaultContext()));

        static::assertCount(3, $this->batches);
        if (Feature::isActive('CACHE_REWORK')) {
            $expected[] = MediaRoute::buildName($media);
        }
        static::assertEqualsCanonicalizing($expected, array_merge(...$this->batches));
    }

    public function testVariantCursorAdvancesBetweenParents(): void
    {
        $media = $this->createMedia();
        $products = [];
        for ($i = 0; $i < 4; ++$i) {
            $products[] = $parent = $this->createProduct();
            $this->assignMedia($parent, $media);
            $products[] = $this->createProduct($parent);
            $products[] = $this->createProduct($parent);
        }

        $this->subscriber->invalidateMedia(new MediaIndexerEvent([$media], Context::createDefaultContext()));

        $expected = array_map(EntityCacheKeyGenerator::buildProductTag(...), $products);
        if (Feature::isActive('CACHE_REWORK')) {
            $expected[] = MediaRoute::buildName($media);
        }
        static::assertEqualsCanonicalizing($expected, array_values(array_unique(array_merge(...$this->batches))));
    }

    public function testDefaultBatchSizeInvalidatesTheFollowingProduct(): void
    {
        $this->subscriber = new CacheInvalidationSubscriber($this->invalidator, $this->connection, productStreamIndexerEnabled: true);
        $media = $this->createMedia();
        $expected = [];
        $queue = new MultiInsertQueryQueue($this->connection, chunkSize: 1000);
        for ($i = 0; $i < 10001; ++$i) {
            $id = Uuid::randomBytes();
            $expected[] = EntityCacheKeyGenerator::buildProductTag(Uuid::fromBytesToHex($id));
            $queue->addInsert('product', ['id' => $id, 'stock' => 0, 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]);
            $queue->addInsert('product_media', [
                'id' => Uuid::randomBytes(),
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'product_id' => $id,
                'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'media_id' => Uuid::fromHexToBytes($media),
                'created_at' => '2026-01-01 00:00:00',
            ]);
            if ($i % 1000 === 999) {
                $queue->execute();
            }
        }
        $queue->execute();

        $this->subscriber->invalidateMedia(new MediaIndexerEvent([$media], Context::createDefaultContext()));

        static::assertCount(2, $this->batches);
        static::assertCount(10000, $this->batches[0]);
        if (Feature::isActive('CACHE_REWORK')) {
            $expected[] = MediaRoute::buildName($media);
        }
        static::assertEqualsCanonicalizing($expected, array_merge(...$this->batches));
    }

    public function testDuplicateAssignmentsAcrossAVersionBoundaryDoNotHideTheFollowingProduct(): void
    {
        $media = $this->createMedia();
        $otherMedia = $this->createMedia();
        $otherProduct = $this->createProduct();
        $this->assignMedia($otherProduct, $otherMedia);
        $parent = $this->createProduct();
        $following = $this->createProduct();
        $variant = $this->createProduct($parent);
        $queue = new MultiInsertQueryQueue($this->connection, chunkSize: 1000);
        $prefix = substr(Uuid::randomBytes(), 0, 12);
        for ($i = 0; $i < 3; ++$i) {
            $queue->addInsert('product_media', [
                'id' => $prefix . pack('N', $i),
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'product_id' => Uuid::fromHexToBytes($parent),
                'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'media_id' => Uuid::fromHexToBytes($media),
                'created_at' => '2026-01-01 00:00:00',
            ]);
        }
        $queue->execute();
        $draft = "\xff" . substr(Uuid::randomBytes(), 1);
        $this->connection->insert('version', ['id' => $draft, 'name' => 'Draft media assignment', 'created_at' => '2026-01-01 00:00:00']);
        $this->connection->insert('product_media', [
            'id' => $prefix . pack('N', 2),
            'version_id' => $draft,
            'product_id' => Uuid::fromHexToBytes($following),
            'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'media_id' => Uuid::fromHexToBytes($media),
            'created_at' => '2026-01-01 00:00:00',
        ]);

        $this->subscriber->invalidateMedia(new MediaIndexerEvent([$media, $otherMedia], Context::createDefaultContext()));

        $expected = array_map(EntityCacheKeyGenerator::buildProductTag(...), [$parent, $variant, $following, $otherProduct]);
        if (Feature::isActive('CACHE_REWORK')) {
            $expected[] = MediaRoute::buildName($media);
            $expected[] = MediaRoute::buildName($otherMedia);
        }
        static::assertEqualsCanonicalizing(
            $expected,
            array_values(array_unique(array_merge(...$this->batches)))
        );
    }

    private function createMedia(): string
    {
        $id = Uuid::randomHex();
        $this->connection->insert('media', ['id' => Uuid::fromHexToBytes($id), 'created_at' => '2026-01-01 00:00:00']);

        return $id;
    }

    private function assignDocument(string $product, string $media): void
    {
        $id = Uuid::randomBytes();
        $version = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $this->connection->insert('product_document', [
            'id' => $id,
            'version_id' => $version,
            'product_id' => Uuid::fromHexToBytes($product),
            'product_version_id' => $version,
            'created_at' => '2026-01-01 00:00:00',
        ]);
        $this->connection->insert('product_document_translation', [
            'product_document_id' => $id,
            'product_document_version_id' => $version,
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'media_id' => Uuid::fromHexToBytes($media),
            'title' => 'Document',
            'created_at' => '2026-01-01 00:00:00',
        ]);
    }

    private function createProduct(?string $parent = null): string
    {
        $id = Uuid::randomHex();
        $this->connection->insert('product', [
            'id' => Uuid::fromHexToBytes($id),
            'stock' => 0,
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'parent_id' => $parent === null ? null : Uuid::fromHexToBytes($parent),
            'parent_version_id' => $parent === null ? null : Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ]);

        return $id;
    }

    private function assignMedia(string $product, string $media): void
    {
        $this->connection->insert('product_media', [
            'id' => Uuid::randomBytes(),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'product_id' => Uuid::fromHexToBytes($product),
            'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'media_id' => Uuid::fromHexToBytes($media),
            'created_at' => '2026-01-01 00:00:00',
        ]);
    }
}
