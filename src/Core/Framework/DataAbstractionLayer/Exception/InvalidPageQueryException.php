<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InvalidPageQueryException extends ShopwareHttpException
{
    public function __construct($page, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('The page parameter must be a positive integer. Given: %s', $page);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
