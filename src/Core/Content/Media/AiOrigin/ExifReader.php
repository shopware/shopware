<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\AiOrigin;

use Shopware\Core\Framework\Log\Package;

/**
 * Reads the textual EXIF tags with PHP's EXIF extension, which is optional.
 * Without it, EXIF signals are skipped; C2PA, XMP and PNG text chunks still work.
 *
 * The extension reads JPEG, TIFF and (PHP 8.5+) HEIF files. WebP and PNG keep their EXIF as a TIFF structure
 * in a chunk, so their readers pass that chunk instead of the whole file.
 *
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:MEDIA_AI_ORIGIN
 */
#[Package('discovery')]
class ExifReader
{
    /**
     * EXIF tags with free text that tools use to name themselves or describe the image.
     */
    private const TEXT_TAGS = ['ImageDescription', 'Make', 'Model', 'Software', 'Artist', 'Copyright', 'UserComment'];

    private const MAX_TEXT_LENGTH = 1024 * 1024;

    /**
     * Character codes of the EXIF user comment, each 8 bytes long, padded with null bytes.
     */
    private const USER_COMMENT_ASCII = "ASCII\0\0\0";

    private const USER_COMMENT_UNICODE = "UNICODE\0";

    /**
     * Eight null bytes: the writer did not name the encoding, in practice ASCII.
     */
    private const USER_COMMENT_UNDEFINED = "\0\0\0\0\0\0\0\0";

    /**
     * @return array<string, string> tag name => value
     */
    public function read(string $jpegTiffOrHeif): array
    {
        $tags = $this->readAllTags($jpegTiffOrHeif);

        $exif = [];
        foreach (self::TEXT_TAGS as $tag) {
            $value = $tags[$tag] ?? null;
            if (!\is_string($value) || \strlen($value) > self::MAX_TEXT_LENGTH) {
                continue;
            }

            if ($tag === 'UserComment') {
                $value = $this->decodeUserComment($value, (bool) ($tags['COMPUTED']['ByteOrderMotorola'] ?? false));
            }

            $value = trim(rtrim($value, "\0"));
            if ($value !== '') {
                $exif[$tag] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }
        }

        return $exif;
    }

    /**
     * @return array<string, mixed>
     */
    private function readAllTags(string $bytes): array
    {
        if (!\function_exists('exif_read_data')) {
            return [];
        }

        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            return [];
        }

        try {
            // in-memory stream, the file was already read through the filesystem abstraction
            fwrite($stream, $bytes);
            rewind($stream);

            $tags = @exif_read_data($stream);
        } catch (\Throwable) {
            return [];
        } finally {
            fclose($stream);
        }

        return \is_array($tags) ? $tags : [];
    }

    /**
     * The EXIF user comment starts with an 8 byte character code that names the encoding of the text after it.
     * The extension returns both undecoded.
     */
    private function decodeUserComment(string $value, bool $bigEndianFile): string
    {
        $comment = new ByteReader($value);
        $characterCode = $comment->readString(8);
        $text = $comment->readRest();

        return match ($characterCode) {
            self::USER_COMMENT_ASCII, self::USER_COMMENT_UNDEFINED => $text,
            self::USER_COMMENT_UNICODE => (string) mb_convert_encoding($text, 'UTF-8', $this->utf16Encoding($text, $bigEndianFile)),
            // "JIS", or a comment written without character code: kept as it is
            default => $value,
        };
    }

    /**
     * EXIF says the byte order of "UNICODE" text is the byte order of the file,
     * but piexif (used by AUTOMATIC1111) always writes big endian.
     * Generation parameters are mostly ASCII, and an ASCII character in UTF-16 has one zero byte:
     * "S" is 0x00 0x53 in big endian and 0x53 0x00 in little endian.
     */
    private function utf16Encoding(string $text, bool $bigEndianFile): string
    {
        if (str_starts_with($text, "\0")) {
            return 'UTF-16BE';
        }

        if (substr($text, 1, 1) === "\0") {
            return 'UTF-16LE';
        }

        return $bigEndianFile ? 'UTF-16BE' : 'UTF-16LE';
    }
}
