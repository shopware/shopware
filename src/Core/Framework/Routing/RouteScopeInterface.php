<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Symfony\Component\HttpFoundation\Request;

interface RouteScopeInterface
{
    public function isAllowedPath(string $path): bool;

    public function isAllowed(Request $request): bool;

    public function getId(): string;
}
