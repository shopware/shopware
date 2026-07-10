<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaType\AudioType;
use Shopware\Core\Content\Media\MediaType\BinaryType;
use Shopware\Core\Content\Media\MediaType\DocumentType;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\MediaType\SpatialObjectType;
use Shopware\Core\Content\Media\MediaType\VideoType;
use Shopware\Core\Content\Media\Metadata\MetadataLoader;
use Shopware\Core\Content\Media\Metadata\MetadataLoader\ImageMetadataLoader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MetadataLoader::class)]
class MetadataLoaderTest extends TestCase
{
    public static function fileTypeDataProvider(): \Generator
    {
        yield 'ImageType' => [
            new MediaFile(
                'foo.bar',
                'image/jpeg',
                'jpg',
                123,
                'image_md5',
            ),
            new ImageType(),
            [
                'hash' => 'image_md5',
            ],
        ];

        yield 'ImageType with metaData' => [
            new MediaFile(
                'foo.bar',
                'image/jpeg',
                'jpg',
                123,
                'image_md5',
            ),
            new ImageType(),
            [
                'type' => \IMAGETYPE_JPEG,
                'width' => 1530,
                'height' => 1021,
                'hash' => 'image_md5',
            ],
            true,
            [
                'type' => \IMAGETYPE_JPEG,
                'width' => 1530,
                'height' => 1021,
            ],
        ];
        yield 'AudioType' => [
            new MediaFile(
                'foo.bar',
                'audio/mpeg',
                'mp3',
                123,
                'audio_md5',
            ),
            new AudioType(),
            [
                'hash' => 'audio_md5',
            ],
        ];

        yield 'VideoType' => [
            new MediaFile(
                'foo.bar',
                'video/mp4',
                'mp4',
                123,
                'video_md5',
            ),
            new VideoType(),
            [
                'hash' => 'video_md5',
            ],
        ];

        yield 'SpatialObjectType' => [
            new MediaFile(
                'foo.bar',
                'application/vnd.google-earth.kml+xml',
                'kml',
                123,
                'spatial_md5',
            ),
            new SpatialObjectType(),
            [
                'hash' => 'spatial_md5',
            ],
        ];

        yield 'DocumentType' => [
            new MediaFile(
                'foo.bar',
                'application/pdf',
                'pdf',
                123,
                'document_md5',
            ),
            new DocumentType(),
            [
                'hash' => 'document_md5',
            ],
        ];

        yield 'BinaryType' => [
            new MediaFile(
                'foo.bar',
                'application/octet-stream',
                'bin',
                123,
                'binary_md5',
            ),
            new BinaryType(),
            [
                'hash' => 'binary_md5',
            ],
        ];

        yield 'BinaryType - without hash' => [
            new MediaFile(
                'foo.bar',
                'application/octet-stream',
                'bin',
                123
            ),
            new BinaryType(),
            null,
        ];
    }

    /**
     * @param array<string, string|int>|null $expected
     * @param array<string, string|int>|null $extractMetadata
     */
    #[DataProvider('fileTypeDataProvider')]
    public function testLoadFromFile(
        MediaFile $file,
        MediaType $type,
        ?array $expected,
        bool $supports = false,
        ?array $extractMetadata = null,
    ): void {
        $loader = $this->createMock(MetadataLoader\MetadataLoaderInterface::class);
        $loader->expects($this->once())
            ->method('supports')
            ->willReturn($supports);
        if ($supports) {
            $loader->expects($this->once())
                ->method('extractMetadata')
                ->with($file->getFileName())
                ->willReturn($extractMetadata);
        }

        $metadataLoader = new MetadataLoader(new \ArrayIterator([$loader]));

        $metadata = $metadataLoader->loadFromFile($file, $type);

        static::assertSame($expected, $metadata);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function realFileDataProvider(): iterable
    {
        $filePath = __DIR__ . '/../fixtures/shopware.jpg';
        yield 'jpg' => [
            $filePath,
            [
                'width' => 1530,
                'height' => 1021,
                'type' => \IMAGETYPE_JPEG,
                'hash' => Hasher::hashFile($filePath, 'md5'),
            ],
        ];

        $filePath = __DIR__ . '/../fixtures/logo.gif';
        yield 'gif' => [
            $filePath,
            [
                'width' => 142,
                'height' => 37,
                'type' => \IMAGETYPE_GIF,
                'hash' => Hasher::hashFile($filePath, 'md5'),
            ],
        ];

        $filePath = __DIR__ . '/../fixtures/shopware-logo.png';
        yield 'png' => [
            $filePath,
            [
                'width' => 499,
                'height' => 266,
                'type' => \IMAGETYPE_PNG,
                'hash' => Hasher::hashFile($filePath, 'md5'),
            ],
        ];

        $filePath = __DIR__ . '/../fixtures/logo-version-professionalplus.svg';
        yield 'svg' => [
            $filePath,
            [
                'hash' => Hasher::hashFile($filePath, 'md5'),
            ],
        ];

        $filePath = __DIR__ . '/../fixtures/small.pdf';
        yield 'pdf' => [
            $filePath,
            [
                'hash' => Hasher::hashFile($filePath, 'md5'),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('realFileDataProvider')]
    public function testLoadFromRealFile(string $filePath, array $expected): void
    {
        $metadataLoader = new MetadataLoader(new \ArrayIterator([new ImageMetadataLoader()]));

        $metadata = $metadataLoader->loadFromFile($this->createMediaFile($filePath), new ImageType());

        static::assertSame($expected, $metadata);
    }

    private function createMediaFile(string $filePath): MediaFile
    {
        $mimeType = mime_content_type($filePath);
        static::assertIsString($mimeType);

        $fileSize = filesize($filePath);
        static::assertIsInt($fileSize);

        return new MediaFile(
            $filePath,
            $mimeType,
            pathinfo($filePath, \PATHINFO_EXTENSION),
            $fileSize,
            Hasher::hashFile($filePath, 'md5')
        );
    }
}
