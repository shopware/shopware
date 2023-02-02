<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MediaNotFoundException extends ShopwareHttpException
{
    public function __construct(string $mediaId)
    {
        parent::__construct(
            'Media for id {{ mediaId }} not found.',
            ['mediaId' => $mediaId]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_NOT_FOUND';
    }
}
