<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class InvalidRouteScopeException extends ShopwareHttpException
{
    public function __construct(string $routeName)
    {
        parent::__construct(
            'Invalid route scope for route {{ routeName }}.',
            ['routeName' => $routeName]
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
