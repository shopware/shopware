<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Validation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @internal
 */
#[Package('framework')]
class RouteBlocklistService
{
    private const CACHE_KEY = 'routing_blocked_routes';
    private const CACHE_TTL = 3600;

    /**
     * @internal
     */
    public function __construct(
        private readonly RouterInterface $router,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * @return list<string>
     */
    public function getBlockedRoutePaths(): array
    {
        return $this->cache->get(
            self::CACHE_KEY,
            function (ItemInterface $item): array {
                $item->expiresAfter(self::CACHE_TTL);

                return $this->extractBlockedPaths();
            }
        );
    }

    public function isPathBlocked(string $path): bool
    {
        $normalizedPath = '/' . trim($path, '/');

        if ($normalizedPath === '/') {
            return true;
        }

        $blockedPaths = $this->getBlockedRoutePaths();

        return \in_array($normalizedPath, $blockedPaths, true);
    }

    public function clearCache(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }

    /**
     * @return list<string>
     */
    private function extractBlockedPaths(): array
    {
        $blockedPaths = [];

        foreach ($this->router->getRouteCollection()->all() as $route) {
            $path = $route->getPath();

            $blockedPaths[] = '/' . \trim($path, '/');
        }

        $blockedPaths = \array_unique($blockedPaths);
        \sort($blockedPaths);

        return $blockedPaths;
    }
}
