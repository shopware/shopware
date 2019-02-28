<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidLimitQueryException extends ShopwareHttpException
{
    public function __construct($limit, int $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('The limit parameter must be a positive integer greater or equals than 1. Given: %s', $limit);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
