<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class FileTypeNotSupportedException extends ShopwareHttpException
{
    public function __construct(string $mediaId)
    {
        parent::__construct(
            'The File for media object with id: {{ mediaId }} is not supported for creating thumbnails.',
            ['mediaId' => $mediaId]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_FILE_TYPE_NOT_SUPPORTED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
