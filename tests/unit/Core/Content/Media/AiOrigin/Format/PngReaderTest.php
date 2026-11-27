<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\AiOrigin\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\AiOrigin\ExifReader;
use Shopware\Core\Content\Media\AiOrigin\Format\PngReader;
use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\Media\AiOrigin\_fixtures\ProvenanceImageBuilder as Image;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(PngReader::class)]
class PngReaderTest extends TestCase
{
    private PngReader $reader;

    protected function setUp(): void
    {
        $this->reader = new PngReader(new ExifReader());
    }

    public function testSupportsPngOnly(): void
    {
        foreach (['png'] as $extension) {
            static::assertTrue($this->reader->supports($extension));
        }

        static::assertFalse($this->reader->supports('jpg'));
    }

    public function testReadsTextChunksXmpAndC2pa(): void
    {
        $xmp = Image::xmp(Image::COMPOSITE);
        $store = Image::c2paStore(['urn:uuid:1' => []]);

        $provenance = $this->reader->read(Image::png([
            'tEXt' => "parameters\0a cat, Steps: 20, Sampler: Euler a",
            'zTXt' => "prompt\0\0" . gzcompress('{"3":{"class_type":"KSampler"}}'),
            'iTXt' => "XML:com.adobe.xmp\0\x01\0\0\0" . gzcompress($xmp),
            'caBX' => $store,
        ]));

        static::assertEquals(new ImageProvenance(
            xmpPackets: [$xmp],
            c2paManifestStores: [$store],
            textChunks: ['parameters' => 'a cat, Steps: 20, Sampler: Euler a', 'prompt' => '{"3":{"class_type":"KSampler"}}'],
        ), $provenance);
    }

    public function testReadsUncompressedInternationalText(): void
    {
        $provenance = $this->reader->read(Image::png(['iTXt' => "Description\0\0\0en\0Beschreibung\0Ein Bild"]));

        static::assertSame(['Description' => 'Ein Bild'], $provenance->textChunks);
    }

    public function testReadsExifFromTheExifChunk(): void
    {
        if (!\function_exists('exif_read_data')) {
            static::markTestSkipped('EXIF is read with ext-exif, which is optional.');
        }

        $provenance = $this->reader->read(Image::png(['eXIf' => Image::exif([0x0131 => 'Stable Diffusion XL'])]));

        static::assertSame(['Software' => 'Stable Diffusion XL'], $provenance->exif);
    }

    public function testCutOffFileOrBrokenCompressionYieldsNoMetadata(): void
    {
        $png = Image::png(['tEXt' => "parameters\0Steps: 20"]);

        static::assertTrue($this->reader->read(substr($png, 0, 40))->isEmpty());
        static::assertTrue($this->reader->read(Image::png(['iTXt' => "XML:com.adobe.xmp\0\x01\0\0\0not zlib"]))->isEmpty());
    }
}
