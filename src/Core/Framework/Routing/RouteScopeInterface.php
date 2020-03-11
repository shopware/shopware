<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.3.0 use abtract Class `AbstractRouteScope` instead
 */
interface RouteScopeInterface
{
    public function isAllowedPath(string $path): bool;

    public function isAllowed(Request $request): bool;

    public function getId(): string;
}
