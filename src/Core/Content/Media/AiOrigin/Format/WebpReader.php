<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\AiOrigin\Format;

use Shopware\Core\Content\Media\AiOrigin\ByteReader;
use Shopware\Core\Content\Media\AiOrigin\ExifReader;
use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Framework\Log\Package;

/**
 * A WebP is a RIFF file: "RIFF", a size and "WEBP", then chunks of a 4 byte type, a little endian 4 byte size and
 * the data, padded to an even length.
 *
 * - XMP: the "XMP " chunk
 * - C2PA: the "C2PA" chunk
 * - EXIF: the "EXIF" chunk, a TIFF structure; the EXIF extension reads whole WebP files only since PHP 8.6
 *
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:MEDIA_AI_ORIGIN
 */
#[Package('discovery')]
class WebpReader implements ImageFormatReader
{
    public function __construct(private readonly ExifReader $exifReader)
    {
    }

    public function supports(string $fileExtension): bool
    {
        return $fileExtension === 'webp';
    }

    public function read(string $bytes): ImageProvenance
    {
        $xmp = [];
        $c2pa = [];
        $exif = [];

        $webp = new ByteReader($bytes, position: 12, littleEndian: true); // after "RIFF", size and "WEBP"
        $webp->readEach(function (ByteReader $webp) use (&$xmp, &$c2pa, &$exif): bool {
            $type = $webp->readString(4);
            $dataLength = $webp->readUint32();

            if ($dataLength > $webp->remaining()) {
                return false; // a cut off file
            }

            $data = new ByteReader($webp->readString($dataLength));
            $webp->skip($dataLength % 2); // padding to an even length

            if ($type === 'XMP ') {
                $xmp[] = $data->readRest();
            } elseif ($type === 'C2PA') {
                $c2pa[] = $data->readRest();
            } elseif ($type === 'EXIF') {
                $data->skipIfNext("Exif\0\0"); // some writers keep the JPEG style header
                $exif = $this->exifReader->read($data->readRest());
            }

            return true;
        });

        return new ImageProvenance(xmpPackets: $xmp, c2paManifestStores: $c2pa, exif: $exif);
    }
}
