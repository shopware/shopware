<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller\Exception;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

#[Package('framework')]
class StorefrontRouteNotFoundException extends RouteNotFoundException
{
    public function __construct(string $route, ?\Throwable $previous = null)
    {
        parent::__construct(
            \sprintf('Route "%s" not found.', $route),
            previous: $previous
        );
    }
}
