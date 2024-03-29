<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mime\MimeTypes;

/**
 * @internal
 */
#[Package('buyers-experience')]
class FileInfoHelper
{
    private const MIME_TYPE_FOR_UNDETECTED_FORMATS = 'application/octet-stream';

    private const COMMON_MIME_TYPES = [
        'text/plain',
        'application/octet-stream',
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
            if (\count($extMimeType) > 0) {
                return $extMimeType[0];
            }
        }

        return $guessedMimeType;
    }
}
