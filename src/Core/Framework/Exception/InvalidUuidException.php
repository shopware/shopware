<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InvalidUuidException extends ShopwareHttpException
{
    public function __construct(string $uuid, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Value is not a valid UUID: %s', $uuid);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
