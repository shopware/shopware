<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\AiOrigin\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Content\Media\AiOrigin\MediaAiOrigin;
use Shopware\Core\Content\Media\AiOrigin\Processor\IptcDigitalSourceTypeProcessor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\Media\AiOrigin\_fixtures\ProvenanceImageBuilder as Image;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(IptcDigitalSourceTypeProcessor::class)]
class IptcDigitalSourceTypeProcessorTest extends TestCase
{
    /**
     * @param list<string> $xmpPackets
     */
    #[DataProvider('xmpPackets')]
    public function testProcess(array $xmpPackets, ?MediaAiOrigin $expected): void
    {
        static::assertSame($expected, (new IptcDigitalSourceTypeProcessor())->process(new ImageProvenance(xmpPackets: $xmpPackets)));
    }

    public static function xmpPackets(): \Generator
    {
        yield 'attribute with trained algorithmic media is generated' => [[Image::xmp(Image::TRAINED)], MediaAiOrigin::Generated];
        yield 'rdf:resource element with any namespace prefix is read' => [[Image::xmp(Image::COMPOSITE, asAttribute: false)], MediaAiOrigin::Modified];
        yield 'element with text content is read' => [
            ['<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><rdf:Description xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/"><Iptc4xmpExt:DigitalSourceType>' . Image::TRAINED . '</Iptc4xmpExt:DigitalSourceType></rdf:Description></rdf:RDF></x:xmpmeta>'],
            MediaAiOrigin::Generated,
        ];
        yield 'generated in any packet wins over modified in an earlier one' => [[Image::xmp(Image::COMPOSITE), Image::xmp(Image::TRAINED)], MediaAiOrigin::Generated];
        yield 'AI system without source type counts as generated' => [[Image::xmpPacket('<Iptc4xmpExt:AISystemUsed>Adobe Firefly</Iptc4xmpExt:AISystemUsed>')], MediaAiOrigin::Generated];
        yield 'AI prompt without source type counts as generated' => [
            [Image::xmpPacket('<Iptc4xmpExt:AIPromptInformation><rdf:Alt><rdf:li xml:lang="x-default">a red sneaker</rdf:li></rdf:Alt></Iptc4xmpExt:AIPromptInformation>')],
            MediaAiOrigin::Generated,
        ];
        yield 'AI system next to a camera source type counts as generated' => [
            [Image::xmpPacket('<Iptc4xmpExt:DigitalSourceType rdf:resource="' . Image::DIGITAL_CAPTURE . '"/><Iptc4xmpExt:AISystemUsed>Imagen</Iptc4xmpExt:AISystemUsed>')],
            MediaAiOrigin::Generated,
        ];
        yield 'an explicit AI source type wins over the AI system' => [
            [Image::xmpPacket('<Iptc4xmpExt:DigitalSourceType rdf:resource="' . Image::COMPOSITE . '"/><Iptc4xmpExt:AISystemUsed>Firefly</Iptc4xmpExt:AISystemUsed>')],
            MediaAiOrigin::Modified,
        ];
        yield 'empty AI system is no signal' => [[Image::xmpPacket('<Iptc4xmpExt:AISystemUsed></Iptc4xmpExt:AISystemUsed>')], null];
        yield 'digital capture is not AI' => [[Image::xmp(Image::DIGITAL_CAPTURE)], null];
        yield 'same local name in another namespace is ignored' => [
            ['<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><rdf:Description xmlns:foo="http://example.com/" foo:DigitalSourceType="' . Image::TRAINED . '"/></rdf:RDF></x:xmpmeta>'],
            null,
        ];
        yield 'invalid XML is ignored' => [['<x:xmpmeta DigitalSourceType="' . Image::TRAINED], null];
        yield 'no XMP is no signal' => [[], null];
    }
}
