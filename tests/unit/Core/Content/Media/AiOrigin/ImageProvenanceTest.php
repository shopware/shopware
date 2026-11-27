<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\AiOrigin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\Media\AiOrigin\_fixtures\ProvenanceImageBuilder as Image;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ImageProvenance::class)]
class ImageProvenanceTest extends TestCase
{
    public function testReadsXmpValuesFromAttributesElementsResourcesAndLanguageAlternatives(): void
    {
        $provenance = new ImageProvenance(xmpPackets: [
            Image::xmpPacket('<xmp:CreatorTool>Firefly</xmp:CreatorTool>'),
            '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
                . '<rdf:Description xmlns:tool="http://ns.adobe.com/xap/1.0/" tool:CreatorTool="Imagen"/></rdf:RDF></x:xmpmeta>',
            Image::xmpPacket('<xmp:CreatorTool rdf:resource="https://example.com/generator"/>'),
            Image::xmpPacket('<xmp:CreatorTool><rdf:Alt><rdf:li xml:lang="x-default"> Midjourney </rdf:li></rdf:Alt></xmp:CreatorTool>'),
        ]);

        static::assertSame(
            ['Firefly', 'Imagen', 'https://example.com/generator', 'Midjourney'],
            $provenance->xmpValues(ImageProvenance::XMP_NAMESPACE, 'CreatorTool'),
        );
    }

    public function testIgnoresTheSamePropertyNameInAnotherNamespace(): void
    {
        $provenance = new ImageProvenance(xmpPackets: [
            Image::xmpPacket('<dc:description><rdf:Alt><rdf:li xml:lang="x-default">a photo</rdf:li></rdf:Alt></dc:description>'),
        ]);

        static::assertSame([], $provenance->xmpValues(ImageProvenance::IPTC_EXTENSION_NAMESPACE, 'description'));
        static::assertSame(['a photo'], $provenance->xmpValues(ImageProvenance::DUBLIN_CORE_NAMESPACE, 'description'));
    }

    public function testSkipsPacketsThatAreNotValidXml(): void
    {
        $provenance = new ImageProvenance(xmpPackets: ['<x:xmpmeta CreatorTool="Firefly"', Image::xmpPacket('<xmp:CreatorTool>Imagen</xmp:CreatorTool>')]);

        static::assertSame(['Imagen'], $provenance->xmpValues(ImageProvenance::XMP_NAMESPACE, 'CreatorTool'));
    }

    public function testIsEmptyWithoutAnyMetadata(): void
    {
        static::assertTrue((new ImageProvenance())->isEmpty());
        static::assertFalse((new ImageProvenance(exif: ['Software' => 'Firefly']))->isEmpty());
        static::assertFalse((new ImageProvenance(textChunks: ['Comment' => 'hi']))->isEmpty());
        static::assertFalse((new ImageProvenance(c2paManifestStores: ['store']))->isEmpty());
        static::assertFalse((new ImageProvenance(xmpPackets: ['<x:xmpmeta/>']))->isEmpty());
    }
}
