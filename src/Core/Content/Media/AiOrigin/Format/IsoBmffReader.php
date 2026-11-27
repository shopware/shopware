<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\AiOrigin\Format;

use Shopware\Core\Content\Media\AiOrigin\ByteReader;
use Shopware\Core\Content\Media\AiOrigin\ExifReader;
use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Framework\Log\Package;

/**
 * AVIF and HEIF are ISO base media files: a list of boxes, starting with "ftyp".
 *
 * - C2PA: a top-level "uuid" box with the C2PA UUID
 * - XMP: an item that only the item location table points to, so it is found by searching the file
 *   outside the C2PA box, which can hold XMP of other images
 * - EXIF: read from the whole file (PHP 8.5+)
 *
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:MEDIA_AI_ORIGIN
 */
#[Package('discovery')]
class IsoBmffReader implements ImageFormatReader
{
    /**
     * C2PA spec, "Embedding manifests into BMFF".
     */
    private const C2PA_UUID = "\xD8\xFE\xC3\xD6\x1B\x0E\x48\x3C\x92\x97\x58\x28\x87\x7E\xC4\x81";

    public function __construct(private readonly ExifReader $exifReader)
    {
    }

    public function supports(string $fileExtension): bool
    {
        return \in_array($fileExtension, ['avif', 'heic', 'heif'], true);
    }

    public function read(string $bytes): ImageProvenance
    {
        $c2pa = [];
        $outsideC2pa = '';

        $boxes = (new ByteReader($bytes))->readAll(static fn (ByteReader $file): ?array => $file->readIsoBox());

        foreach ($boxes as [$type, $payload]) {
            $box = new ByteReader($payload);
            if ($type !== 'uuid' || !$box->skipIfNext(self::C2PA_UUID)) {
                $outsideC2pa .= $payload;

                continue;
            }

            $box->skip(4); // version and flags
            if ($box->readNullTerminatedString() === 'manifest') {
                $box->skip(8); // offset of the merkle tree
                $c2pa[] = $box->readRest();
            }
        }

        $xmp = (new ByteReader($outsideC2pa))->findEnclosed('<x:xmpmeta', '</x:xmpmeta>');

        return new ImageProvenance(
            xmpPackets: $xmp === null ? [] : [$xmp],
            c2paManifestStores: $c2pa,
            exif: $this->exifReader->read($bytes),
        );
    }
}
