<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mime\MimeTypes;

/**
 * @internal
 */
#[Package('discovery')]
class FileInfoHelper
{
    private const MIME_TYPE_FOR_UNDETECTED_FORMATS = 'application/octet-stream';

    private const COMMON_MIME_TYPES = [
        'text/plain',
        'application/octet-stream',
    ];

    /**
     * Text-based MIME types that must be stored/served with an explicit `charset=utf-8`, otherwise browsers
     * fall back to a non-UTF-8 default encoding and render multi-byte characters (ä, ö, ü, ß, …) as mojibake
     * when the object is served directly from S3/CDN.
     *
     * IMPORTANT: This list is duplicated on the client for the presigned direct-to-S3 upload flow. The value
     * presigned by the server ({@see \Shopware\Core\Content\Media\Upload\PresignedUploadUrlGenerator}) and the
     * `Content-Type` header the browser sends on the PUT must match byte-for-byte, or S3 rejects the upload with
     * `SignatureDoesNotMatch`. Keep this in sync with `TEXT_BASED_MIME_TYPES` in
     * src/Administration/Resources/app/administration/src/core/service/api/media-presigned-upload.api.service.js
     */
    private const TEXT_BASED_MIME_TYPES = [
        'text/plain',
        'text/csv',
        'text/html',
        'text/xml',
        'application/json',
        'application/xml',
    ];

    public static function getMimeType(string $fileName, ?string $originalExtension = null): string
    {
        $mimeTypesDetector = new MimeTypes();
        $guessedMimeType = $mimeTypesDetector->guessMimeType($fileName) ?? self::MIME_TYPE_FOR_UNDETECTED_FORMATS;

        if ($originalExtension === null) {
            return $guessedMimeType;
        }

        if (\in_array($guessedMimeType, self::COMMON_MIME_TYPES, true)) {
            $extMimeType = $mimeTypesDetector->getMimeTypes($originalExtension);
            if ($extMimeType !== []) {
                return $extMimeType[0];
            }
        }

        return $guessedMimeType;
    }

    /**
     * Returns the canonical `Content-Type` value used when writing a file to storage: text-based types get an
     * explicit `; charset=utf-8`, all other types are returned unchanged. Only apply this at the storage/HTTP
     * boundary — the persisted `mimeType` on the media entity must stay bare, since consumers such as media-type
     * and extension detection rely on it (e.g. `explode('/', $mimeType)`).
     */
    public static function addCharset(string $mimeType): string
    {
        if (\in_array($mimeType, self::TEXT_BASED_MIME_TYPES, true)) {
            return $mimeType . '; charset=utf-8';
        }

        return $mimeType;
    }

    /**
     * Strips any parameters (e.g. `; charset=utf-8`) from a `Content-Type`, yielding the bare MIME type suitable
     * for persisting on the media entity. Inverse of {@see self::addCharset()} for storage read-back.
     */
    public static function stripParameters(string $contentType): string
    {
        return trim(explode(';', $contentType)[0]);
    }

    public static function getExtension(string $mimeType): string
    {
        $mimeTypesDetector = new MimeTypes();
        $extensions = $mimeTypesDetector->getExtensions($mimeType);

        if (!isset($extensions[0])) {
            throw MediaException::invalidMimeType($mimeType);
        }

        return $extensions[0];
    }
}
