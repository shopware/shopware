<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('content')]
class MissingFileException extends ShopwareHttpException
{
    public function __construct(string $mediaId)
    {
        parent::__construct(
            'Could not find file for media with id: "{{ mediaId }}"',
            ['mediaId' => $mediaId]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_MISSING_FILE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
