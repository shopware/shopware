<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;

/**
 * Provides cache policies and resolves which policy to use for a given request
 *
 * @internal
 *
 * @phpstan-import-type CacheAttribute from PlatformRequest
 */
#[Package('framework')]
readonly class CachePolicyProvider
{
    /**
     * @param array<string, CachePolicy> $policies
     * @param array<string, string> $routePolicies
     * @param array<string, DefaultPolicies> $defaultPolicies
     */
    public function __construct(
        private array $policies,
        private array $routePolicies,
        private array $defaultPolicies,
    ) {
    }

    /**
     * Get policy for area and route
     *
     * @param string $route Route name
     * @param string $area Area (storefront, store_api)
     * @param bool $cacheable Whether the response is cacheable
     * @param CacheAttribute $cacheAttribute cache attribute from request
     *
     * @return CachePolicy The resolved policy or default no-cache policy
     */
    public function getPolicy(string $route, string $area, bool $cacheable, array|bool|null $cacheAttribute = null): CachePolicy
    {
        $policyModifier = $cacheAttribute['policyModifier'] ?? null;
        $policyName = $this->resolvePolicyName($route, $area, $cacheable, $policyModifier);

        return $this->policies[$policyName] ?? CachePolicy::noCache();
    }

    /**
     * Resolve policy name based on route, area, and cacheability
     *
     * Priority:
     * 1. Route with modifier (route#modifier) for script hooks
     * 2. Route-level policy
     * 3. Area defaults (cacheable/uncacheable)
     */
    private function resolvePolicyName(string $route, string $area, bool $cacheable, ?string $policyModifier): ?string
    {
        // Priority 1: Route with modifier (e.g., "frontend.script_endpoint#hookName")
        if ($policyModifier !== null && $route !== '') {
            $modifiedRouteKey = $route . '#' . $policyModifier;
            if (isset($this->routePolicies[$modifiedRouteKey])) {
                return $this->routePolicies[$modifiedRouteKey];
            }
        }

        // Priority 2: Route-level override
        if ($route !== '' && isset($this->routePolicies[$route])) {
            return $this->routePolicies[$route];
        }

        // Priority 3: Area defaults
        $areaDefaults = $this->defaultPolicies[$area] ?? null;
        if ($areaDefaults === null) {
            return null;
        }

        return $cacheable ? $areaDefaults->cacheablePolicyName : $areaDefaults->uncacheablePolicyName;
    }
}
