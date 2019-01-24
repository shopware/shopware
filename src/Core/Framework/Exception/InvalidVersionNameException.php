<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InvalidVersionNameException extends ShopwareHttpException
{
    protected $code = 'INVALID-VERSION-NAME';

    public function __construct($code = 0, Throwable $previous = null)
    {
        $message = sprintf('Invalid version name given. Only alphanumeric characters are allowed');

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
