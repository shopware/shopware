<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class IllegalMimeTypeException extends ShopwareHttpException
{
    public function __construct(string $mimeType, $code = 0, Throwable $previous = null)
    {
        parent::__construct("Mime-type '{$mimeType}' is not supported by this action", $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_UNSUPPORTED_MEDIA_TYPE;
    }
}
