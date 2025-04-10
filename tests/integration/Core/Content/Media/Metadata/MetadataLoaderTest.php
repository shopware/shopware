<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Metadata;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\Metadata\MetadataLoader;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;

/**
 * @internal
 */
class MetadataLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @return iterable<string, array<string, mixed>>
     */
    public static function fileDataProvider(): iterable
    {
        $imgPath = __DIR__ . '/../fixtures/shopware.jpg';
        yield 'jpg' => [
            'imgPath' => $imgPath,
            'expected' => [
                'type' => \IMAGETYPE_JPEG,
                'width' => 1530,
                'height' => 1021,
                'hash' => Hasher::hashFile($imgPath, 'md5'),
            ],
        ];

        $imgPath = __DIR__ . '/../fixtures/logo.gif';
        yield 'gif' => [
            'imgPath' => $imgPath,
            'expected' => [
                'type' => \IMAGETYPE_GIF,
                'width' => 142,
                'height' => 37,
                'hash' => Hasher::hashFile($imgPath, 'md5'),
            ],
        ];

        $imgPath = __DIR__ . '/../fixtures/shopware-logo.png';
        yield 'png' => [
            'imgPath' => $imgPath,
            'expected' => [
                'type' => \IMAGETYPE_PNG,
                'width' => 499,
                'height' => 266,
                'hash' => Hasher::hashFile($imgPath, 'md5'),
            ],
        ];

        $imgPath = __DIR__ . '/../fixtures/logo-version-professionalplus.svg';
        yield 'svg' => [
            'imgPath' => $imgPath,
            'expected' => [
                'hash' => Hasher::hashFile($imgPath, 'md5'),
            ],
        ];

        $imgPath = __DIR__ . '/../fixtures/small.pdf';
        yield 'pdf' => [
            'imgPath' => $imgPath,
            'expected' => [
                'hash' => Hasher::hashFile($imgPath, 'md5'),
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $expected
     */
    #[DataProvider('fileDataProvider')]
    public function testLoadFromFile(
        string $imgPath,
        ?array $expected
    ): void {
        $result = $this
            ->getMetadataLoader()
            ->loadFromFile($this->createMediaFile($imgPath), new ImageType());

        static::assertIsArray($result);
        static::assertEquals($expected, $result);
    }

    private function getMetadataLoader(): MetadataLoader
    {
        return static::getContainer()
            ->get(MetadataLoader::class);
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
