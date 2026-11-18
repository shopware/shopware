<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;
use Shopware\Core\Content\Media\SalesChannel\MediaRoute;
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

    /**
     * @var list<list<string>>
     */
    private array $batches = [];

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $invalidator = static::createStub(CacheInvalidator::class);
        $invalidator->method('invalidate')->willReturnCallback(function (array $tags): void {
            /** @var list<string> $tags */
            $this->batches[] = $tags;
        });
        $this->subscriber = new CacheInvalidationSubscriber($invalidator, $this->connection, true);
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
        for ($i = 0; $i < 100001; ++$i) {
            $id = Uuid::randomHex();
            $expected[] = EntityCacheKeyGenerator::buildProductTag($id);
            $queue->addInsert('product', [
                'id' => Uuid::fromHexToBytes($id),
                'stock' => 0,
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'parent_id' => $parent === null ? null : Uuid::fromHexToBytes($parent),
                'parent_version_id' => $parent === null ? null : Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
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
            if ($i % 1000 === 999) {
                $queue->execute();
            }
        }
        $queue->execute();

        $this->subscriber->invalidateMedia(new MediaIndexerEvent([$media], Context::createDefaultContext()));

        static::assertCount($variants ? 100001 : 100000, $this->batches[0]);
        static::assertCount($variants ? 2 : 1, $this->batches[1]);
        $productTags = array_merge($this->batches[0], $this->batches[1]);
        static::assertEqualsCanonicalizing($expected, array_values(array_unique($productTags)));
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function largeCatalogs(): iterable
    {
        yield 'one parent with 100001 variants' => [true];
        yield '100001 products without variants' => [false];
    }

    public function testMediaWithoutProductsStillInvalidatesItsOwnTag(): void
    {
        $media = $this->createMedia();

        $this->subscriber->invalidateMedia(new MediaIndexerEvent([$media], Context::createDefaultContext()));

        static::assertSame(Feature::isActive('CACHE_REWORK') ? [[MediaRoute::buildName($media)]] : [], $this->batches);
    }

    private function createMedia(): string
    {
        $id = Uuid::randomHex();
        $this->connection->insert('media', ['id' => Uuid::fromHexToBytes($id), 'created_at' => '2026-01-01 00:00:00']);

        return $id;
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
