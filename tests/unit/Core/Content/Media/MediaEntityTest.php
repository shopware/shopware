<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\SpatialObjectType;
use Shopware\Core\Content\Media\MediaType\SpatialSceneType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaEntity::class)]
class MediaEntityTest extends TestCase
{
    #[DataProvider('filenameExtensionProvider')]
    public function testGetFilenameIncludingExtension(?string $file, ?string $ext, ?string $expected): void
    {
        $media = new MediaEntity();

        if ($file) {
            $media->setFileName($file);
        }

        if ($ext) {
            $media->setFileExtension($ext);
        }

        static::assertSame($expected, $media->getFileNameIncludingExtension());
    }

    /**
     * @return iterable<string, array{file: ?string, ext: ?string, expected: ?string}>
     */
    public static function filenameExtensionProvider(): iterable
    {
        yield 'only-ext' => ['file' => null, 'ext' => 'jpg', 'expected' => null];
        yield 'only-file' => ['file' => 'Tuscany-Landscape', 'ext' => null, 'expected' => null];
        yield 'file-and-ext' => ['file' => 'Tuscany-Landscape', 'ext' => 'jpg', 'expected' => 'Tuscany-Landscape.jpg'];
    }

    public function testHasFileRequiresTheFileMetadataOrAPath(): void
    {
        $media = new MediaEntity();
        static::assertFalse($media->hasFile());
        static::assertFalse($media->hasPath());
        static::assertSame('', $media->getPath());

        $media->setPath('media/tuscany.jpg');
        static::assertTrue($media->hasFile());
        static::assertTrue($media->hasPath());
        static::assertSame('media/tuscany.jpg', $media->getPath());

        $withMetadata = new MediaEntity();
        $withMetadata->setMimeType('image/jpeg');
        $withMetadata->setFileExtension('jpg');
        $withMetadata->setFileName('tuscany');
        static::assertTrue($withMetadata->hasFile());
    }

    public function testGetResolvesHasFileAsAProperty(): void
    {
        $media = new MediaEntity();
        $media->setPath('media/tuscany.jpg');
        $media->setTitle('Tuscany');

        static::assertTrue($media->get('hasFile'));
        static::assertSame('Tuscany', $media->get('title'));
    }

    public function testIsSpatialSceneOnlyMatchesTheSceneType(): void
    {
        $media = new MediaEntity();

        static::assertFalse($media->isSpatialScene());

        $media->setMediaType(new ImageType());
        static::assertFalse($media->isSpatialScene());

        // A scene and an object are both spatial, but only one of them carries a file.
        $media->setMediaType(new SpatialObjectType());
        static::assertFalse($media->isSpatialScene());
        static::assertTrue($media->isSpatialObject());

        $media->setMediaType(new SpatialSceneType());
        static::assertTrue($media->isSpatialScene());
        static::assertFalse($media->isSpatialObject());
    }

    public function testJsonSerializeHidesTheRawDataAndAddsHasFile(): void
    {
        $media = new MediaEntity();
        $media->setMediaTypeRaw('media-type-raw');
        $media->setMetaDataRaw('meta-data-raw');

        static::assertSame('media-type-raw', $media->getMediaTypeRaw());

        $data = $media->jsonSerialize();

        static::assertArrayNotHasKey('mediaTypeRaw', $data);
        static::assertArrayNotHasKey('metaDataRaw', $data);
        static::assertFalse($data['hasFile']);
    }
}
