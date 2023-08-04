<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\MediaEntity
 */
class MediaEntityTest extends TestCase
{
    /**
     * @dataProvider filenameExtensionProvider
     */
    public function testGetFilenameIncludingExtension(?string $file, ?string $ext, ?string $expected): void
    {
        $media = new MediaEntity();

        if ($file) {
            $media->setFileName($file);
        }

        if ($ext) {
            $media->setFileExtension($ext);
        }

        static::assertEquals($expected, $media->getFileNameIncludingExtension());
    }

    /**
     * @return array<string, array{file: ?string, ext: ?string, expected: ?string}>
     */
    public static function filenameExtensionProvider(): array
    {
        return [
            'only-ext' => ['file' => null, 'ext' => 'jpg', 'expected' => null],
            'only-file' => ['file' => 'Tuscany-Landscape', 'ext' => null, 'expected' => null],
            'file-and-ext' => ['file' => 'Tuscany-Landscape', 'ext' => 'jpg', 'expected' => 'Tuscany-Landscape.jpg'],
        ];
    }
}
