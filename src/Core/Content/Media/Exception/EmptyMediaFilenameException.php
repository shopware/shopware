<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class EmptyMediaFilenameException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('A valid Filename must be provided.');
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_EMPTY_FILE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
