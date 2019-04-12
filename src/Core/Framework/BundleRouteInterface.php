<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Symfony\Component\Routing\RouteCollectionBuilder;

interface BundleRouteInterface
{
    public function configureRoutes(RouteCollectionBuilder $routes, string $environment): void;
}
