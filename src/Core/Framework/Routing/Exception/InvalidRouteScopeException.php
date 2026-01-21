<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class InvalidRouteScopeException extends RoutingException
{
    public function __construct(?string $routeName)
    {
        parent::__construct(
            Response::HTTP_PRECONDITION_FAILED,
            parent::INVALID_ROUTE_SCOPE,
            'Invalid route scope for route {{ routeName }}.',
            ['routeName' => $routeName ?? '']
        );
    }
}
