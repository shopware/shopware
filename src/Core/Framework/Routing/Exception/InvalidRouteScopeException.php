<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidRouteScopeException extends ShopwareHttpException
{
    public function __construct(string $routeName, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Invalid route scope for route {{ routeName }}.',
            ['routeName' => $routeName],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__ROUTING_INVALID_ROUTE_SCOPE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_PRECONDITION_FAILED;
    }
}
