<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\AiOrigin\_fixtures;

use Shopware\Core\Framework\Log\Package;

/**
 * Builds minimal image files that carry provenance metadata the way AI providers write it.
 * The image data itself is not decodable, the reader only looks at the containers.
 *
 * @internal
 */
#[Package('discovery')]
class ProvenanceImageBuilder
{
    public const TRAINED = 'http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia';

    public const COMPOSITE = 'http://cv.iptc.org/newscodes/digitalsourcetype/compositeWithTrainedAlgorithmicMedia';

    public const DIGITAL_CAPTURE = 'http://cv.iptc.org/newscodes/digitalsourcetype/digitalCapture';

    /**
     * A complete XMP packet around the content of one `rdf:Description`.
     * The prefixes `xmp`, `dc` and `Iptc4xmpExt` are declared.
     */
    public static function xmpPacket(string $description): string
    {
        return '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about="" xmlns:xmp="http://ns.adobe.com/xap/1.0/" xmlns:dc="http://purl.org/dc/elements/1.1/"'
            . ' xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/">'
            . $description
            . '</rdf:Description></rdf:RDF></x:xmpmeta>';
    }

    public static function xmp(string $digitalSourceType, bool $asAttribute = true): string
    {
        $value = $asAttribute
            ? \sprintf('<rdf:Description rdf:about="" xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/" Iptc4xmpExt:DigitalSourceType="%s"/>', $digitalSourceType)
            : \sprintf('<rdf:Description rdf:about="" xmlns:ext="http://iptc.org/std/Iptc4xmpExt/2008-02-29/"><ext:DigitalSourceType rdf:resource="%s"/></rdf:Description>', $digitalSourceType);

        return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?><x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . $value
            . '</rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
    }

    /**
     * @param array<string, array{actions?: list<array<string, mixed>>, templates?: list<array<string, mixed>>, ingredients?: list<array<string, mixed>>}> $manifests in store order, the last one is active
     */
    public static function c2paStore(array $manifests): string
    {
        $manifestBoxes = '';
        foreach ($manifests as $label => $manifest) {
            $assertions = '';
            if (isset($manifest['actions'])) {
                $assertions .= self::superbox('c2pa.actions.v2', self::box('cbor', self::cbor(['actions' => $manifest['actions'], 'templates' => $manifest['templates'] ?? []])));
            }
            foreach ($manifest['ingredients'] ?? [] as $index => $ingredient) {
                $assertions .= self::superbox($index === 0 ? 'c2pa.ingredient.v3' : 'c2pa.ingredient.v3__' . $index, self::box('cbor', self::cbor($ingredient)));
            }

            $claim = self::cbor(['claim_generator_info' => ['name' => 'Test']]);

            $manifestBoxes .= self::superbox($label, self::superbox('c2pa.assertions', $assertions) . self::superbox('c2pa.claim.v2', self::box('cbor', $claim)));
        }

        return self::superbox('c2pa', $manifestBoxes);
    }

    /**
     * @return array<string, mixed>
     */
    public static function ingredient(string $relationship, string $manifestLabel): array
    {
        return ['relationship' => $relationship, 'activeManifest' => ['url' => 'self#jumbf=/c2pa/' . $manifestLabel, 'hash' => 'x']];
    }

    /**
     * @param list<string> $xmpPackets
     * @param list<string> $c2paSegments JUMBF data, already split into APP11 payloads with {@see self::app11Segments()}
     */
    public static function jpeg(array $xmpPackets = [], array $c2paSegments = [], ?string $exif = null, ?string $comment = null): string
    {
        $jpeg = "\xFF\xD8" . self::jpegSegment(0xE0, "JFIF\0\x01\x01\0\0\x01\0\x01\0\0");

        foreach ($xmpPackets as $xmp) {
            $jpeg .= self::jpegSegment(0xE1, "http://ns.adobe.com/xap/1.0/\0" . $xmp);
        }
        if ($exif !== null) {
            $jpeg .= self::jpegSegment(0xE1, "Exif\0\0" . $exif);
        }
        foreach ($c2paSegments as $segment) {
            $jpeg .= self::jpegSegment(0xEB, $segment);
        }
        if ($comment !== null) {
            $jpeg .= self::jpegSegment(0xFE, $comment);
        }

        // image data after SOS is never read
        return $jpeg . "\xFF\xDA" . pack('n', 2) . "\x00\x01\x02\xFF\xD9";
    }

    /**
     * Splits a JUMBF box into JPEG APP11 payloads like c2patool does. Every segment repeats the box header.
     *
     * @param positive-int $chunkSize
     *
     * @return list<string>
     */
    public static function app11Segments(string $jumbf, int $chunkSize = 65000): array
    {
        $header = substr($jumbf, 0, 8);
        $chunks = str_split(substr($jumbf, 8), $chunkSize);
        $segments = [];

        foreach ($chunks as $index => $chunk) {
            $segments[] = 'JP' . pack('n', 1) . pack('N', $index + 1) . $header . $chunk;
        }

        return $segments;
    }

    /**
     * @param array<string, string> $chunks chunk type => data, for example ['tEXt' => "parameters\0..."]
     */
    public static function png(array $chunks): string
    {
        $png = "\x89PNG\r\n\x1a\n" . self::pngChunk('IHDR', pack('NNCCCCC', 1, 1, 8, 2, 0, 0, 0));

        foreach ($chunks as $type => $data) {
            $png .= self::pngChunk(substr($type, 0, 4), $data);
        }

        return $png . self::pngChunk('IEND', '');
    }

    public static function pngChunk(string $type, string $data): string
    {
        return pack('N', \strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    /**
     * @param array<string, string> $chunks fourcc => data
     */
    public static function webp(array $chunks): string
    {
        $body = 'WEBPVP8X' . pack('V', 10) . str_repeat("\0", 10);
        foreach ($chunks as $fourcc => $data) {
            $body .= $fourcc . pack('V', \strlen($data)) . $data . (\strlen($data) % 2 === 1 ? "\0" : '');
        }

        return 'RIFF' . pack('V', \strlen($body)) . $body;
    }

    public static function avif(?string $c2paStore = null, ?string $xmp = null): string
    {
        $avif = self::box('ftyp', 'avif' . pack('N', 0) . 'avifmif1');
        if ($xmp !== null) {
            $avif .= self::box('mdat', $xmp);
        }
        if ($c2paStore !== null) {
            $uuid = "\xD8\xFE\xC3\xD6\x1B\x0E\x48\x3C\x92\x97\x58\x28\x87\x7E\xC4\x81";
            $avif .= self::box('uuid', $uuid . "\0\0\0\0manifest\0" . pack('J', 0) . $c2paStore);
        }

        return $avif;
    }

    /**
     * TIFF structure with IFD0 ASCII tags and an optional user comment in the EXIF IFD, little endian.
     *
     * @param array<int, string> $asciiTags tag id => value
     * @param 'ASCII'|'UTF-16BE'|'UTF-16LE' $userCommentEncoding UTF-16 is written with the character code "UNICODE"
     */
    public static function exif(array $asciiTags, ?string $userComment = null, string $userCommentEncoding = 'ASCII'): string
    {
        $entryCount = \count($asciiTags) + ($userComment === null ? 0 : 1);
        $dataOffset = 8 + 2 + $entryCount * 12 + 4;
        $entries = '';
        $data = '';

        foreach ($asciiTags as $tag => $value) {
            $value .= "\0";
            $entries .= pack('vvV', $tag, 2, \strlen($value)) . pack('V', $dataOffset + \strlen($data));
            $data .= $value;
        }

        if ($userComment === null) {
            return 'II' . pack('v', 42) . pack('V', 8) . pack('v', $entryCount) . $entries . pack('V', 0) . $data;
        }

        // pointer to the EXIF IFD, which holds the user comment (type UNDEFINED, 8 byte character code first)
        $exifIfdOffset = $dataOffset + \strlen($data);
        $entries .= pack('vvVV', 0x8769, 4, 1, $exifIfdOffset);
        $comment = $userCommentEncoding === 'ASCII'
            ? "ASCII\0\0\0" . $userComment
            : "UNICODE\0" . mb_convert_encoding($userComment, $userCommentEncoding, 'UTF-8');
        $exifIfd = pack('v', 1) . pack('vvVV', 0x9286, 7, \strlen($comment), $exifIfdOffset + 2 + 12 + 4) . pack('V', 0) . $comment;

        return 'II' . pack('v', 42) . pack('V', 8) . pack('v', $entryCount) . $entries . pack('V', 0) . $data . $exifIfd;
    }

    public static function box(string $type, string $payload): string
    {
        return pack('N', 8 + \strlen($payload)) . $type . $payload;
    }

    public static function superbox(string $label, string $content): string
    {
        // description box: content type UUID, toggles (requestable + label), label
        $description = self::box('jumd', str_repeat("\x11", 16) . "\x03" . $label . "\0");

        return self::box('jumb', $description . $content);
    }

    public static function cbor(mixed $value): string
    {
        if ($value === null) {
            return "\xF6";
        }

        if (\is_bool($value)) {
            return $value ? "\xF5" : "\xF4";
        }

        if (\is_int($value)) {
            return $value >= 0 ? self::cborHead(0, $value) : self::cborHead(1, -1 - $value);
        }

        if (\is_string($value)) {
            return self::cborHead(3, \strlen($value)) . $value;
        }

        if (\is_array($value) && array_is_list($value)) {
            return self::cborHead(4, \count($value)) . implode('', array_map(self::cbor(...), $value));
        }

        if (\is_array($value)) {
            $map = self::cborHead(5, \count($value));
            foreach ($value as $key => $item) {
                $map .= self::cbor($key) . self::cbor($item);
            }

            return $map;
        }

        throw new \InvalidArgumentException('Unsupported CBOR value');
    }

    private static function cborHead(int $major, int $argument): string
    {
        $major <<= 5;

        if ($argument < 24) {
            return \chr($major | $argument);
        }

        if ($argument < 0x100) {
            return \chr($major | 24) . \chr($argument);
        }

        if ($argument < 0x10000) {
            return \chr($major | 25) . pack('n', $argument);
        }

        return \chr($major | 26) . pack('N', $argument);
    }

    private static function jpegSegment(int $marker, string $payload): string
    {
        return "\xFF" . \chr($marker) . pack('n', \strlen($payload) + 2) . $payload;
    }
}
