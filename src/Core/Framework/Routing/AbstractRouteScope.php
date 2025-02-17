<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
abstract class AbstractRouteScope
{
    /**
     * @var list<string>
     */
    protected array $allowedPaths = [];

    public function isAllowedPath(string $path): bool
    {
        $basePath = explode('/', $path);

        return empty($this->allowedPaths) || \in_array($basePath[1], $this->allowedPaths, true);
    }

    abstract public function isAllowed(Request $request): bool;

    abstract public function getId(): string;

    /**
     * @return list<string>
     */
    public function getRoutePrefixes(): array
    {
        return $this->allowedPaths;
    }
}
