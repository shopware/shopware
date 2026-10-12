<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Aggregate\MediaThumbnail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaThumbnailEntity::class)]
class MediaThumbnailEntityTest extends TestCase
{
    public function testExposesItsData(): void
    {
        $media = new MediaEntity();
        $size = new MediaThumbnailSizeEntity();

        $entity = new MediaThumbnailEntity();
        $entity->setWidth(120);
        $entity->setHeight(80);
        $entity->setUrl('https://shop.example/thumbnail.png');
        $entity->setMediaId('media-id');
        $entity->setMedia($media);
        $entity->setMediaThumbnailSizeId('size-id');
        $entity->setMediaThumbnailSize($size);
        $entity->setPath('thumbnail/path.png');

        static::assertSame(120, $entity->getWidth());
        static::assertSame(80, $entity->getHeight());
        static::assertSame('https://shop.example/thumbnail.png', $entity->getUrl());
        static::assertSame('media-id', $entity->getMediaId());
        static::assertSame($media, $entity->getMedia());
        static::assertSame('size-id', $entity->getMediaThumbnailSizeId());
        static::assertSame($size, $entity->getMediaThumbnailSize());
        static::assertSame('120x80', $entity->getIdentifier());
        static::assertSame('thumbnail/path.png', $entity->getPath());
    }

    public function testGetUrlFallsBackToAnEmptyString(): void
    {
        $entity = new MediaThumbnailEntity();
        $entity->assign(['url' => null]);

        static::assertSame('', $entity->getUrl());
    }

    public function testGetMediaIdThrowsWhenUnsetAndFeatureActive(): void
    {
        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: $mediaId must not be null'));
        (new MediaThumbnailEntity())->getMediaId();
    }

    public function testGetMediaThumbnailSizeIdThrowsWhenUnsetAndFeatureActive(): void
    {
        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: $mediaThumbnailSizeId must not be null'));
        (new MediaThumbnailEntity())->getMediaThumbnailSizeId();
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testUnsetIdsFallBackToLegacyDefaults(): void
    {
        $entity = new MediaThumbnailEntity();

        static::assertSame('', $entity->getMediaId());
        static::assertNull($entity->getMediaThumbnailSizeId());
    }
}
