<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class QueryLimitExceededException extends ShopwareHttpException
{
    public function __construct(
        ?int $maxLimit,
        ?int $limit
    ) {
        parent::__construct(
            'The limit must be lower than or equal to MAX_LIMIT(={{ maxLimit }}). Given: {{ limit }}',
            ['maxLimit' => $maxLimit, 'limit' => $limit]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__QUERY_LIMIT_EXCEEDED';
    }
}
