<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Pathname;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\PathGenerator;
use Shopware\Core\Content\Media\Pathname\PathnameStrategy\FilenamePathnameStrategy;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\File\FileSaver
 */
class PathGeneratorTest extends TestCase
{
    public function testMediaPath(): void
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->assign(
            [
                'id' => Uuid::randomHex(),
                'fileName' => 'file',
                'fileExtension' => 'jpg',
            ]
        );

        $pathGenerator = new PathGenerator(new FilenamePathnameStrategy());

        static::assertSame(
            'media/8c/7d/d9/file.jpg',
            $pathGenerator->generatePath($mediaEntity)
        );
    }

    public function testMediaThumbnailPath(): void
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->assign(
            [
                'id' => Uuid::randomHex(),
                'fileName' => 'file',
                'fileExtension' => 'jpg',
            ]
        );

        $mediaThumbnailEntity = new MediaThumbnailEntity();
        $mediaThumbnailEntity->assign(
            [
                'width' => 100,
                'height' => 100,
            ]
        );

        $pathGenerator = new PathGenerator(new FilenamePathnameStrategy());
        static::assertSame(
            'thumbnail/8c/7d/d9/file_100x100.jpg',
            $pathGenerator->generatePath($mediaEntity, $mediaThumbnailEntity)
        );
    }
}
