<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\AiOrigin\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\AiOrigin\ExifReader;
use Shopware\Core\Content\Media\AiOrigin\Format\WebpReader;
use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\Media\AiOrigin\_fixtures\ProvenanceImageBuilder as Image;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(WebpReader::class)]
class WebpReaderTest extends TestCase
{
    private WebpReader $reader;

    protected function setUp(): void
    {
        $this->reader = new WebpReader(new ExifReader());
    }

    public function testSupportsWebpOnly(): void
    {
        foreach (['webp'] as $extension) {
            static::assertTrue($this->reader->supports($extension));
        }

        static::assertFalse($this->reader->supports('wav'));
    }

    public function testReadsXmpC2paAndExif(): void
    {
        if (!\function_exists('exif_read_data')) {
            static::markTestSkipped('EXIF is read with ext-exif, which is optional.');
        }

        $xmp = Image::xmp(Image::TRAINED);
        $store = Image::c2paStore(['urn:uuid:1' => []]);

        $provenance = $this->reader->read(Image::webp([
            'EXIF' => "Exif\0\0" . Image::exif([0x0131 => 'Samsung Generative Edit']),
            'XMP ' => $xmp,
            'C2PA' => $store,
        ]));

        static::assertEquals(new ImageProvenance(
            xmpPackets: [$xmp],
            c2paManifestStores: [$store],
            exif: ['Software' => 'Samsung Generative Edit'],
        ), $provenance);
    }
}
