<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('content')]
class ThumbnailNotSupportedException extends ShopwareHttpException
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
