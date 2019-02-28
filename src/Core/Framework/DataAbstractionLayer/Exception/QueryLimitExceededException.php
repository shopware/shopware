<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class QueryLimitExceededException extends ShopwareHttpException
{
    public function __construct($maxLimit, $limit, int $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('The limit must be lower than or equal to MAX_LIMIT(=%d). Given: %s', $maxLimit, $limit);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
