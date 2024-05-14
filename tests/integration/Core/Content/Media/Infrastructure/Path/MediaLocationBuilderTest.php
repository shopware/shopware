<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Infrastructure\Path;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Core\Event\MediaLocationEvent;
use Shopware\Core\Content\Media\Core\Event\ThumbnailLocationEvent;
use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\Core\Params\ThumbnailLocationStruct;
use Shopware\Core\Content\Media\Infrastructure\Path\SqlMediaLocationBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\Stub\EventDispatcher\AssertingEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(SqlMediaLocationBuilder::class)]
#[CoversClass(MediaLocationEvent::class)]
#[CoversClass(MediaLocationStruct::class)]
#[CoversClass(ThumbnailLocationStruct::class)]
class MediaLocationBuilderTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @param array<string, mixed> $storage
     */
    #[DataProvider('buildMediaProvider')]
    public function testBuildMedia(array $storage, MediaLocationStruct $expected): void
    {
        $ids = new IdsCollection();

        $storage['id'] = $ids->getBytes('media');
        $storage['created_at'] = '2022-01-01';

        $queue = new MultiInsertQueryQueue($this->getContainer()->get(Connection::class));
        $queue->addInsert('media', $storage);
        $queue->execute();

        $dispatcher = new AssertingEventDispatcher($this, [MediaLocationEvent::class => 1]);

        $builder = new SqlMediaLocationBuilder($dispatcher, $this->getContainer()->get(Connection::class));

        $locations = $builder->media($ids->getList(['media']));

        static::assertArrayHasKey($ids->get('media'), $locations);

        $location = $locations[$ids->get('media')];
        $expected->id = $ids->get('media');

        static::assertEquals($expected, $location);
    }

    /**
     * @param array<string, mixed> $media
     * @param array<string, mixed> $thumbnail
     */
    #[DataProvider('buildThumbnailProvider')]
    public function testBuildThumbnails(array $media, array $thumbnail, ThumbnailLocationStruct $expected): void
    {
        $ids = new IdsCollection();

        $media['id'] = $ids->getBytes('media');
        $thumbnail['id'] = $ids->getBytes('thumbnail');
        $thumbnail['media_id'] = $ids->getBytes('media');

        $queue = new MultiInsertQueryQueue($this->getContainer()->get(Connection::class));
        $queue->addInsert('media', $media);
        $queue->addInsert('media_thumbnail', $thumbnail);
        $queue->execute();

        $dispatcher = new AssertingEventDispatcher($this, [ThumbnailLocationEvent::class => 1]);

        $builder = new SqlMediaLocationBuilder($dispatcher, $this->getContainer()->get(Connection::class));

        $locations = $builder->thumbnails($ids->getList(['thumbnail']));

        static::assertArrayHasKey($ids->get('thumbnail'), $locations);

        $location = $locations[$ids->get('thumbnail')];
        $expected->id = $ids->get('thumbnail');
        $expected->media->id = $ids->get('media');

        static::assertEquals($expected, $location);
    }

    public function testThumbnailEventAllowsExtension(): void
    {
        $ids = new IdsCollection();

        $queue = new MultiInsertQueryQueue($this->getContainer()->get(Connection::class));

        $queue->addInsert('media', [
            'id' => $ids->getBytes('media'),
            'file_name' => 'test-file-1',
            'file_extension' => 'png',
            'created_at' => '2022-01-01',
        ]);

        $queue->addInsert('media_thumbnail', [
            'id' => $ids->getBytes('thumbnail'),
            'media_id' => $ids->getBytes('media'),
            'width' => 100,
            'height' => 100,
            'created_at' => '2022-01-01',
        ]);

        $queue->execute();

        $dispatcher = new EventDispatcher();

        $dispatcher->addListener(ThumbnailLocationEvent::class, function (ThumbnailLocationEvent $event) use ($ids): void {
            static::assertArrayHasKey($ids->get('thumbnail'), $event->locations);

            foreach ($event as &$location) {
                $location->media->fileName = 'foo';
                $location->addExtension('foo', new ArrayStruct());
            }
        });

        $builder = new SqlMediaLocationBuilder($dispatcher, $this->getContainer()->get(Connection::class));
        $locations = $builder->thumbnails($ids->getList(['thumbnail']));

        static::assertArrayHasKey($ids->get('thumbnail'), $locations);

        $location = $locations[$ids->get('thumbnail')];

        static::assertEquals('foo', $location->media->fileName);
        static::assertTrue($location->hasExtension('foo'));
    }

    public function testCallingWithEmptyIdsDoesNotExecuteDatabaseQuery(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(static::never())
            ->method('fetchAllAssociativeIndexed');

        $builder = new SqlMediaLocationBuilder(new EventDispatcher(), $connection);
        $builder->media([]);
        $builder->thumbnails([]);
    }

    public function testMediaEventAllowsExtension(): void
    {
        $ids = new IdsCollection();

        $queue = new MultiInsertQueryQueue($this->getContainer()->get(Connection::class));
        $queue->addInsert('media', [
            'id' => $ids->getBytes('media'),
            'file_name' => 'test-file-1',
            'file_extension' => 'png',
            'created_at' => '2022-01-01',
        ]);

        $queue->execute();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(MediaLocationEvent::class, function (MediaLocationEvent $event) use ($ids): void {
            static::assertArrayHasKey($ids->get('media'), $event->locations);

            foreach ($event as &$location) {
                $location->fileName = 'foo';
                $location->addExtension('foo', new ArrayStruct());
            }
        });

        $builder = new SqlMediaLocationBuilder($dispatcher, $this->getContainer()->get(Connection::class));

        $locations = $builder->media($ids->getList(['media']));

        static::assertArrayHasKey($ids->get('media'), $locations);

        $location = $locations[$ids->get('media')];

        static::assertEquals('foo', $location->fileName);
        static::assertTrue($location->hasExtension('foo'));
    }

    public static function buildMediaProvider(): \Generator
    {
        yield 'Build location for only file_name and extension' => [
            ['file_name' => 'foo', 'file_extension' => 'jpg', 'created_at' => '2022-01-01'],
            new MediaLocationStruct('', 'jpg', 'foo', null),
        ];

        yield 'Build location with uploaded_at' => [
            ['file_name' => 'foo', 'file_extension' => 'jpg', 'uploaded_at' => '2022-01-01', 'created_at' => '2022-01-01'],
            new MediaLocationStruct('', 'jpg', 'foo', new \DateTimeImmutable('2022-01-01')),
        ];

        yield 'Build location without any data' => [
            ['created_at' => '2022-01-01'],
            new MediaLocationStruct('', '', '', null),
        ];
    }

    public static function buildThumbnailProvider(): \Generator
    {
        yield 'Build location with 0 values' => [
            ['created_at' => '2022-01-01'],
            ['width' => 0, 'height' => 0, 'created_at' => '2022-01-01'],
            new ThumbnailLocationStruct('', 0, 0, new MediaLocationStruct('', '', '', null)),
        ];

        yield 'Build location and media location' => [
            ['file_name' => 'foo', 'file_extension' => 'jpg', 'uploaded_at' => '2022-01-01', 'created_at' => '2022-01-01'],
            ['width' => 100, 'height' => 100, 'created_at' => '2022-01-01'],
            new ThumbnailLocationStruct('', 100, 100, new MediaLocationStruct('', 'jpg', 'foo', new \DateTimeImmutable('2022-01-01'))),
        ];
    }
}
