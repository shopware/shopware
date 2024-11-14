<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Core\Application;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Core\Application\MediaLocationBuilder;
use Shopware\Core\Content\Media\Core\Application\MediaPathStorage;
use Shopware\Core\Content\Media\Core\Application\MediaPathUpdater;
use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\Core\Params\ThumbnailLocationStruct;
use Shopware\Core\Content\Media\Core\Strategy\PlainPathStrategy;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(MediaPathUpdater::class)]
class MediaPathUpdaterTest extends TestCase
{
    public function testUpdateMedia(): void
    {
        $ids = new IdsCollection();

        $strategy = new PlainPathStrategy();

        $locations = [
            new MediaLocationStruct($ids->get('media-1'), 'png', 'media_1', null),
            new MediaLocationStruct($ids->get('media-2'), 'png', 'media_2', null),
        ];

        $builder = $this->createMock(MediaLocationBuilder::class);
        $builder->expects(static::once())
            ->method('media')
            ->with($ids->getList(['media-1', 'media-2']))
            ->willReturn($locations);

        $storage = $this->createMock(MediaPathStorage::class);

        $storage->expects(static::once())
            ->method('media')
            ->with([
                $ids->get('media-1') => 'media/media_1.png',
                $ids->get('media-2') => 'media/media_2.png',
            ]);

        $updater = new MediaPathUpdater($strategy, $builder, $storage);

        $updater->updateMedia($ids->getList(['media-1', 'media-2']));
    }

    public function testSkipUpdateWithoutFilename(): void
    {
        $ids = new IdsCollection();

        $strategy = new PlainPathStrategy();

        $locations = [
            new MediaLocationStruct($ids->get('media-1'), 'png', null, null),
            new MediaLocationStruct($ids->get('media-2'), 'png', null, null),
        ];

        $builder = $this->createMock(MediaLocationBuilder::class);
        $builder->expects(static::once())
            ->method('media')
            ->with($ids->getList(['media-1', 'media-2']))
            ->willReturn($locations);

        $storage = $this->createMock(MediaPathStorage::class);

        $storage->expects(static::never())
            ->method('media');

        $updater = new MediaPathUpdater($strategy, $builder, $storage);

        $updater->updateMedia($ids->getList(['media-1', 'media-2']));
    }

    public function testUpdateThumbnail(): void
    {
        $ids = new IdsCollection();

        $strategy = new PlainPathStrategy();

        $locations = [
            new ThumbnailLocationStruct($ids->get('thumbnail-1'), 100, 100, new MediaLocationStruct($ids->get('media-1'), 'png', 'media_1', null)),
            new ThumbnailLocationStruct($ids->get('thumbnail-2'), 100, 100, new MediaLocationStruct($ids->get('media-2'), 'png', 'media_2', null)),
        ];

        $builder = $this->createMock(MediaLocationBuilder::class);

        $builder->expects(static::once())
            ->method('thumbnails')
            ->with($ids->getList(['thumbnail-1', 'thumbnail-2']))
            ->willReturn($locations);

        $storage = $this->createMock(MediaPathStorage::class);

        $storage->expects(static::once())
            ->method('thumbnails')
            ->with([
                $ids->get('thumbnail-1') => 'thumbnail/media_1_100x100.png',
                $ids->get('thumbnail-2') => 'thumbnail/media_2_100x100.png',
            ]);

        $updater = new MediaPathUpdater($strategy, $builder, $storage);

        $updater->updateThumbnails($ids->getList(['thumbnail-1', 'thumbnail-2']));
    }

    public function testThumbnailUpdateWhenMediaContainsNoFileName(): void
    {
        $ids = new IdsCollection();

        $strategy = new PlainPathStrategy();

        $locations = [
            new ThumbnailLocationStruct($ids->get('thumbnail-1'), 100, 100, new MediaLocationStruct($ids->get('media-1'), 'png', null, null)),
            new ThumbnailLocationStruct($ids->get('thumbnail-2'), 100, 100, new MediaLocationStruct($ids->get('media-2'), 'png', null, null)),
        ];

        $builder = $this->createMock(MediaLocationBuilder::class);

        $builder->expects(static::once())
            ->method('thumbnails')
            ->with($ids->getList(['thumbnail-1', 'thumbnail-2']))
            ->willReturn($locations);

        $storage = $this->createMock(MediaPathStorage::class);

        $storage->expects(static::never())
            ->method('thumbnails');

        $updater = new MediaPathUpdater($strategy, $builder, $storage);

        $updater->updateThumbnails($ids->getList(['thumbnail-1', 'thumbnail-2']));
    }
}
