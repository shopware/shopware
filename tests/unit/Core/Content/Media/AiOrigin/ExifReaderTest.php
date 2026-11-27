<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\AiOrigin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\AiOrigin\ExifReader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\Media\AiOrigin\_fixtures\ProvenanceImageBuilder as Image;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ExifReader::class)]
class ExifReaderTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\function_exists('exif_read_data')) {
            static::markTestSkipped('EXIF is read with ext-exif, which is optional.');
        }
    }

    public function testReadsTextTagsOfATiffStructure(): void
    {
        $exif = (new ExifReader())->read(Image::exif([0x0131 => 'ComfyUI', 0x010F => 'workflow:{"nodes":[]}']));

        static::assertSame(['Make' => 'workflow:{"nodes":[]}', 'Software' => 'ComfyUI'], $exif);
    }

    /**
     * @param 'ASCII'|'UTF-16BE'|'UTF-16LE' $encoding
     */
    #[DataProvider('userCommentEncodings')]
    public function testDecodesTheUserComment(string $encoding): void
    {
        $parameters = 'a red sneaker, Steps: 30, Sampler: DPM++ 2M Karras, CFG scale: 7, Größe: 1024';

        $exif = (new ExifReader())->read(Image::jpeg(exif: Image::exif([], userComment: $parameters, userCommentEncoding: $encoding)));

        static::assertSame(['UserComment' => $parameters], $exif);
    }

    public static function userCommentEncodings(): \Generator
    {
        yield 'ASCII character code' => ['ASCII'];
        yield 'UNICODE in big endian, as piexif writes it in every file' => ['UTF-16BE'];
        yield 'UNICODE in the little endian byte order of the file' => ['UTF-16LE'];
    }

    public function testDataWithoutExifYieldsNothing(): void
    {
        static::assertSame([], (new ExifReader())->read('not an image'));
    }
}
