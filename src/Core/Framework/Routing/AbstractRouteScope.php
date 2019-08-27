<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Symfony\Component\HttpFoundation\Request;

abstract class AbstractRouteScope implements RouteScopeInterface
{
    /**
     * @var string[]
     */
    protected $allowedPaths = [];

    public function isAllowedPath(string $path): bool
    {
        $basePath = explode('/', $path);
        if (empty($this->allowedPaths) || in_array($basePath[1], $this->allowedPaths, true)) {
            return true;
        }

        return false;
    }

    abstract public function isAllowed(Request $request): bool;

    abstract public function getId(): string;
}
