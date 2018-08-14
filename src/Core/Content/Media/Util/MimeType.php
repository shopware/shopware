<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Util;

use Shopware\Core\Content\Media\Exception\IllegalMimeTypeException;

class MimeType
{
    private const MIME_TYPES = [
        'image/png' => '.png',
        'image/tiff' => '.tiff',
        'image/jpeg' => '.jpg',
        'image/jpg' => '.jpg',
        'image/gif' => '.gif',
        'image/bmp' => '.bmp',
        'image/svg+xml' => '.svg',

        'video/mpeg' => '.mp4',
        'video/mp4' => '.mp4',
        'video/webm' => '.webm',
        'video/ogg' => '.ogv',
        'video/quicktime' => '.mov',
        'video/x-msvideo' => '.avi',

        'audio/mpeg' => '.mp3',
        'audio/webm' => '.webm',
        'audio/ogg' => '.ogg',
        'audio/wav' => '.wav',

        'application/pdf' => '.pdf',
        'application/msword' => '.doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
        'application/vnd.ms-excel' => '.xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '.xlsx',
        'application/vnd.ms-powerpoint' => '.ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => '.pptx',
    ];

    public static function isSupported(?string $mimeType): bool
    {
        if (!$mimeType) {
            return false;
        }

        return isset(self::MIME_TYPES[$mimeType]);
    }

    /**
     * @throws IllegalMimeTypeException
     */
    public static function getExtension(?string $mimeType): string
    {
        if (self::isSupported($mimeType)) {
            return self::MIME_TYPES[$mimeType];
        }

        throw new IllegalMimeTypeException($mimeType);
    }
}
