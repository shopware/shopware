<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MissingParameterException extends ShopwareHttpException
{
    protected $code = 'MISSING-PARAMETER';

    public function __construct(string $name, $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Parameter "%s" is missing', $name);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
