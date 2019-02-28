<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidUuidLengthException extends ShopwareHttpException
{
    protected $code = 'UUID-INVALID-LENGTH';

    public function __construct(int $length, string $hex, $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('UUID has a invalid length. 16 bytes expected, %s given. Hexadecimal reprensentation: %s', $length, $hex);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
