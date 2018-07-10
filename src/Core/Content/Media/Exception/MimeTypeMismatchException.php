<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MimeTypeMismatchException extends ShopwareHttpException
{
    public function __construct(string $headerType, string $fileType, $code = 0, Throwable $previous = null)
    {
        parent::__construct("Content-type '{$headerType}' sent in Header does not match Mime-Type '{$fileType}' of binary", $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_UNSUPPORTED_MEDIA_TYPE;
    }
}
