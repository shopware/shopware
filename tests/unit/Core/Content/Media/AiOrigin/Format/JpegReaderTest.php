<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\AiOrigin\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\AiOrigin\ExifReader;
use Shopware\Core\Content\Media\AiOrigin\Format\JpegReader;
use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\Media\AiOrigin\_fixtures\ProvenanceImageBuilder as Image;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(JpegReader::class)]
class JpegReaderTest extends TestCase
{
    private JpegReader $reader;

    protected function setUp(): void
    {
        $this->reader = new JpegReader(new ExifReader());
    }

    public function testSupportsJpegOnly(): void
    {
        foreach (['jpg', 'jpeg'] as $extension) {
            static::assertTrue($this->reader->supports($extension));
        }

        static::assertFalse($this->reader->supports('png'));
    }

    public function testReadsXmpCommentAndC2paSplitIntoSeveralSegments(): void
    {
        $xmp = Image::xmp(Image::TRAINED);
        $store = Image::c2paStore(['urn:uuid:1' => ['actions' => [['action' => 'c2pa.created', 'digitalSourceType' => Image::TRAINED]]]]);

        $provenance = $this->reader->read(Image::jpeg(
            xmpPackets: [$xmp],
            c2paSegments: Image::app11Segments($store, chunkSize: 50),
            comment: 'Made with AI',
        ));

        static::assertEquals(
            new ImageProvenance(xmpPackets: [$xmp], c2paManifestStores: [$store], textChunks: ['Comment' => 'Made with AI']),
            $provenance,
        );
    }

    public function testReadsExifOfTheWholeFile(): void
    {
        if (!\function_exists('exif_read_data')) {
            static::markTestSkipped('EXIF is read with ext-exif, which is optional.');
        }

        $provenance = $this->reader->read(Image::jpeg(exif: Image::exif([0x0131 => 'Adobe Firefly', 0x010F => 'Google'])));

        static::assertSame(['Make' => 'Google', 'Software' => 'Adobe Firefly'], $provenance->exif);
    }

    public function testCutOffFileYieldsNoMetadata(): void
    {
        $jpeg = Image::jpeg(xmpPackets: [Image::xmp(Image::TRAINED)]);

        static::assertTrue($this->reader->read(substr($jpeg, 0, 30))->isEmpty());
    }
}
