<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MethodNotOverriddenException extends ShopwareHttpException
{
    protected $code = 'METHOD-NOT-OVERRIDDEN';

    public function __construct(string $method, string $class, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('The %s method of %s requires to be overridden', $method, $class);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
