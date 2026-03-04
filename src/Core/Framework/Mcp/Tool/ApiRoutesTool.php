<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Routing\RouterInterface;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-api-routes', description: 'List available Shopware API routes. Optionally filter by a path prefix (e.g. "/api/product").')]
#[Package('framework')]
class ApiRoutesTool
{
    public function __construct(
        private readonly RouterInterface $router,
    ) {
    }

    public function __invoke(string $prefix = '/api'): string
    {
        $routes = [];

        foreach ($this->router->getRouteCollection() as $name => $route) {
            $path = $route->getPath();

            if (!str_starts_with($path, $prefix)) {
                continue;
            }

            $routes[] = [
                'name' => $name,
                'path' => $path,
                'methods' => $route->getMethods() ?: ['ANY'],
            ];
        }

        usort($routes, fn (array $a, array $b) => $a['path'] <=> $b['path']);

        return json_encode([
            'total' => \count($routes),
            'routes' => $routes,
        ], \JSON_THROW_ON_ERROR);
    }
}
