<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidParameterException extends ShopwareHttpException
{
    protected $code = 'INVALID-PARAMETER';

    public function __construct(string $name, int $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('The parameter "%s" is invalid', $name);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
