<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('content')]
class EmptyMediaIdException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('A media id must be provided.');
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_EMPTY_ID';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}
