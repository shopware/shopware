<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InvalidOffsetQueryException extends ShopwareHttpException
{
    public function __construct($offset, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('The offset parameter must be a positive integer. Given: %s', $offset);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
