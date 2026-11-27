<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\AiOrigin\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\AiOrigin\ExifReader;
use Shopware\Core\Content\Media\AiOrigin\Format\IsoBmffReader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\Media\AiOrigin\_fixtures\ProvenanceImageBuilder as Image;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(IsoBmffReader::class)]
class IsoBmffReaderTest extends TestCase
{
    private IsoBmffReader $reader;

    protected function setUp(): void
    {
        $this->reader = new IsoBmffReader(new ExifReader());
    }

    public function testSupportsIsoBaseMediaFilesOnly(): void
    {
        foreach (['avif', 'heic', 'heif'] as $extension) {
            static::assertTrue($this->reader->supports($extension));
        }

        static::assertFalse($this->reader->supports('mp4'));
    }

    public function testReadsC2paBoxAndXmpOutsideOfIt(): void
    {
        $xmp = Image::xmp(Image::TRAINED);
        // the ingredient thumbnail inside the store carries its own XMP, which must not be picked up
        $store = Image::c2paStore(['urn:uuid:1' => []]) . Image::xmp(Image::COMPOSITE);

        $provenance = $this->reader->read(Image::avif(c2paStore: $store, xmp: $xmp));

        static::assertSame([$store], $provenance->c2paManifestStores);
        static::assertCount(1, $provenance->xmpPackets);
        static::assertStringContainsString('trainedAlgorithmicMedia', $provenance->xmpPackets[0]);
    }
}
