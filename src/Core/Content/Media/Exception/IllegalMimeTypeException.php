<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class IllegalMimeTypeException extends ShopwareHttpException
{
    protected $code = 'MIME_TYPE_NOT_FOUND_EXCEPTION';

    public function __construct(string $mimeType, int $code = 0, Throwable $previous = null)
    {
        parent::__construct("Mime-type '{$mimeType}' is not supported by this action", $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_UNSUPPORTED_MEDIA_TYPE;
    }
}
