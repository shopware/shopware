<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\AiOrigin\Format;

use Shopware\Core\Content\Media\AiOrigin\ByteReader;
use Shopware\Core\Content\Media\AiOrigin\ExifReader;
use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Framework\Log\Package;

/**
 * A PNG is a signature, then chunks: a 4 byte length, a 4 byte type, the data and a 4 byte CRC.
 *
 * - XMP: an iTXt text chunk with the keyword "XML:com.adobe.xmp"
 * - C2PA: the caBX chunk
 * - text: tEXt, zTXt (compressed) and iTXt (UTF-8) chunks, where Stable Diffusion tools write their parameters
 * - EXIF: the eXIf chunk
 *
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:MEDIA_AI_ORIGIN
 */
#[Package('discovery')]
class PngReader implements ImageFormatReader
{
    private const SIGNATURE = "\x89PNG\r\n\x1a\n";

    private const XMP_KEYWORD = 'XML:com.adobe.xmp';

    /**
     * Longer text is ignored, and compressed text is not inflated beyond it, which protects against decompression bombs.
     */
    private const MAX_TEXT_LENGTH = 1024 * 1024;

    public function __construct(private readonly ExifReader $exifReader)
    {
    }

    public function supports(string $fileExtension): bool
    {
        return $fileExtension === 'png';
    }

    public function read(string $bytes): ImageProvenance
    {
        $xmp = [];
        $c2pa = [];
        $text = [];
        $exif = [];

        $png = new ByteReader($bytes, position: \strlen(self::SIGNATURE));
        $png->readEach(function (ByteReader $png) use (&$xmp, &$c2pa, &$text, &$exif): bool {
            $dataLength = $png->readUint32();
            $type = $png->readString(4);

            if ($type === 'IEND' || $dataLength > $png->remaining() - 4) {
                return false; // end of the image, or a cut off file
            }

            $data = $png->readString($dataLength);
            $png->skip(4); // CRC

            if ($type === 'caBX') {
                $c2pa[] = $data;
            } elseif ($type === 'eXIf') {
                $exif = $this->exifReader->read($data);
            } elseif ($type === 'tEXt' || $type === 'zTXt' || $type === 'iTXt') {
                [$keyword, $value] = $this->readText($type, $data);

                if ($keyword === self::XMP_KEYWORD) {
                    $xmp[] = $value;
                } elseif ($keyword !== '' && \strlen($value) <= self::MAX_TEXT_LENGTH) {
                    $text[$keyword] = $value;
                }
            }

            return true;
        });

        return new ImageProvenance(xmpPackets: $xmp, c2paManifestStores: $c2pa, textChunks: $text, exif: $exif);
    }

    /**
     * - tEXt: keyword, null, Latin-1 text
     * - zTXt: keyword, null, compression method, zlib-compressed Latin-1 text
     * - iTXt: keyword, null, compression flag, compression method, language tag, null, translated keyword, null, UTF-8 text
     *
     * @return array{string, string} keyword and UTF-8 text, an empty keyword if the text cannot be read
     */
    private function readText(string $type, string $data): array
    {
        $chunk = new ByteReader($data);
        $keyword = $chunk->readNullTerminatedString() ?? '';

        if ($type === 'tEXt') {
            return [$keyword, mb_convert_encoding($chunk->readRest(), 'UTF-8', 'ISO-8859-1')];
        }

        if ($type === 'zTXt') {
            $chunk->skip(1); // compression method, always zlib
            $text = $this->inflate($chunk->readRest());

            return $text === null ? ['', ''] : [$keyword, mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1')];
        }

        $compressed = $chunk->readByte() === 1;
        $chunk->skip(1); // compression method, always zlib
        $chunk->readNullTerminatedString(); // language tag
        $chunk->readNullTerminatedString(); // translated keyword
        $text = $compressed ? $this->inflate($chunk->readRest()) : $chunk->readRest();

        return $text === null ? ['', ''] : [$keyword, $text];
    }

    /**
     * Broken compressed data makes zlib warn; an error handler that turns warnings into exceptions must not fail the upload.
     */
    private function inflate(string $data): ?string
    {
        try {
            $inflated = @gzuncompress($data, self::MAX_TEXT_LENGTH);
        } catch (\Throwable) {
            return null;
        }

        return $inflated === false ? null : $inflated;
    }
}
