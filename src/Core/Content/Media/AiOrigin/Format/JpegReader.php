<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\AiOrigin\Format;

use Shopware\Core\Content\Media\AiOrigin\ByteReader;
use Shopware\Core\Content\Media\AiOrigin\ExifReader;
use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Framework\Log\Package;

/**
 * A JPEG is a list of segments: 0xFF, a marker byte that names the segment, a 2 byte length that counts itself,
 * then the payload. Metadata segments come before the image data.
 *
 * - XMP: APP1 segments, large XMP is split into further "extended XMP" APP1 segments
 * - C2PA: APP11 segments, a manifest store is split into numbered parts
 * - text: the comment segment
 * - EXIF: read from the whole file
 *
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:MEDIA_AI_ORIGIN
 */
#[Package('discovery')]
class JpegReader implements ImageFormatReader
{
    private const START_OF_IMAGE = "\xFF\xD8";

    private const APP1 = 0xE1;

    private const APP11 = 0xEB;

    private const COMMENT = 0xFE;

    private const START_OF_SCAN = 0xDA;

    private const END_OF_IMAGE = 0xD9;

    private const XMP_SIGNATURE = "http://ns.adobe.com/xap/1.0/\0";

    private const EXTENDED_XMP_SIGNATURE = "http://ns.adobe.com/xmp/extension/\0";

    public function __construct(private readonly ExifReader $exifReader)
    {
    }

    public function supports(string $fileExtension): bool
    {
        return $fileExtension === 'jpg' || $fileExtension === 'jpeg';
    }

    public function read(string $bytes): ImageProvenance
    {
        $xmp = [];
        $text = [];
        /** @var array<string, array<int, string>> $extendedXmp */
        $extendedXmp = [];
        /** @var array<int, array<int, string>> $c2paParts */
        $c2paParts = [];

        $jpeg = new ByteReader($bytes, position: \strlen(self::START_OF_IMAGE));
        $jpeg->readEach(static function (ByteReader $jpeg) use (&$xmp, &$text, &$extendedXmp, &$c2paParts): bool {
            if (!$jpeg->skipIfNext("\xFF")) {
                return false; // every segment starts with 0xFF
            }

            $marker = $jpeg->readByte();

            if ($marker === 0xFF) {
                $jpeg->skip(-1); // a fill byte: the next 0xFF starts the marker

                return true;
            }

            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD8)) {
                return true; // a marker without a segment
            }

            if ($marker === self::START_OF_SCAN || $marker === self::END_OF_IMAGE) {
                return false;
            }

            $payloadLength = $jpeg->readUint16() - 2; // the length counts its own 2 bytes
            if ($payloadLength < 0) {
                return false;
            }

            $payload = $jpeg->readString($payloadLength);
            $segment = new ByteReader($payload);

            if ($marker === self::APP1 && $segment->skipIfNext(self::XMP_SIGNATURE)) {
                $xmp[] = $segment->readRest();
            } elseif ($marker === self::APP1 && $segment->skipIfNext(self::EXTENDED_XMP_SIGNATURE)) {
                $guid = $segment->readString(32);
                $segment->skip(4); // full length
                $offset = $segment->readUint32();
                $extendedXmp[$guid][$offset] = $segment->readRest();
            } elseif ($marker === self::APP11 && $segment->skipIfNext('JP')) {
                $instance = $segment->readUint16();
                $sequence = $segment->readUint32();

                if ($sequence > 1) {
                    // every following part repeats the box header (size and type, 8 more bytes for a large size)
                    $segment->skip($segment->readUint32() === 1 ? 12 : 4);
                }

                $c2paParts[$instance][$sequence] = $segment->readRest();
            } elseif ($marker === self::COMMENT) {
                $text['Comment'] = $payload;
            }

            return true;
        });

        return new ImageProvenance(
            xmpPackets: [...$xmp, ...$this->joinParts($extendedXmp)],
            c2paManifestStores: $this->joinParts($c2paParts),
            textChunks: $text,
            exif: $this->exifReader->read($bytes),
        );
    }

    /**
     * @param array<int|string, array<int, string>> $partsById parts by their position, which can arrive in any order
     *
     * @return list<string>
     */
    private function joinParts(array $partsById): array
    {
        $joined = [];
        foreach ($partsById as $parts) {
            ksort($parts);
            $joined[] = implode('', $parts);
        }

        return $joined;
    }
}
