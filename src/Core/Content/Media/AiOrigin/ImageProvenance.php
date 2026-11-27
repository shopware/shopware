<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\AiOrigin;

use Shopware\Core\Framework\Log\Package;

/**
 * The raw provenance containers found in an image file.
 * Processors read from it; none of the data is validated or trusted.
 *
 * @experimental stableVersion:v6.8.0 feature:MEDIA_AI_ORIGIN
 */
#[Package('discovery')]
final readonly class ImageProvenance
{
    public const XMP_NAMESPACE = 'http://ns.adobe.com/xap/1.0/';

    public const DUBLIN_CORE_NAMESPACE = 'http://purl.org/dc/elements/1.1/';

    public const IPTC_EXTENSION_NAMESPACE = 'http://iptc.org/std/Iptc4xmpExt/2008-02-29/';

    private const RDF_NAMESPACE = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

    /**
     * @param list<string> $xmpPackets Serialized XMP (RDF/XML) packets
     * @param list<string> $c2paManifestStores Raw C2PA manifest stores as JUMBF boxes
     * @param array<string, string> $textChunks Text entries by keyword, for example PNG `tEXt`/`iTXt` chunks or the JPEG comment
     * @param array<string, string> $exif Textual EXIF tags by tag name, for example `Software` or `Make`
     */
    public function __construct(
        public array $xmpPackets = [],
        public array $c2paManifestStores = [],
        public array $textChunks = [],
        public array $exif = [],
    ) {
    }

    /**
     * The values of one XMP property in all packets, for example `xmpValues(self::XMP_NAMESPACE, 'CreatorTool')`.
     * XMP can write a value as an attribute, as element text (also inside language alternatives) or as `rdf:resource`;
     * all three are read. Packets that are not valid XML are skipped.
     *
     * @return list<string>
     */
    public function xmpValues(string $namespace, string $property): array
    {
        $values = [];

        foreach ($this->xmpPackets as $packet) {
            $document = new \DOMDocument();
            if ($packet === '' || !str_contains($packet, $property) || !$document->loadXML($packet, \LIBXML_NONET | \LIBXML_NOERROR | \LIBXML_NOWARNING)) {
                continue;
            }

            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('property', $namespace);

            foreach ($xpath->query(\sprintf('//@property:%1$s | //property:%1$s', $property)) ?: [] as $node) {
                $value = $node instanceof \DOMElement && $node->hasAttributeNS(self::RDF_NAMESPACE, 'resource')
                    ? $node->getAttributeNS(self::RDF_NAMESPACE, 'resource')
                    : $node->nodeValue;

                $value = trim($value ?? '');
                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }

    public function isEmpty(): bool
    {
        return $this->xmpPackets === []
            && $this->c2paManifestStores === []
            && $this->textChunks === []
            && $this->exif === [];
    }
}
