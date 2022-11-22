<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * @package content
 */
class ThumbnailNotSupportedException extends FileTypeNotSupportedException
{
    public function __construct(string $mediaId)
    {
        parent::__construct(
            'The file for media object with id {{ mediaId }} is not supported for creating thumbnails.',
            ['mediaId' => $mediaId]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_FILE_NOT_SUPPORTED_FOR_THUMBNAIL';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
