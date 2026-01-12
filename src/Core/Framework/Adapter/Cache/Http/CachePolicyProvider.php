<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Log\Package;

/**
 * Provides cache policies and resolves which policy to use for a given request
 *
 * @internal
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
     * Get policy based on route, area, and cacheability.
     *
     * Priority:
     * 1. Route with modifier (route#modifier) for script hooks
     * 2. Route-level policy
     * 3. Area defaults (cacheable/uncacheable). Overrides from CacheAttribute are applied at this stage.
     *
     * @param string $route Route name
     * @param string $area Area (storefront, store_api)
     * @param bool $cacheable Whether the response is cacheable
     * @param bool $enforceNoStore Whether no-store should be enforced
     *
     * @return CachePolicy The resolved policy or default no-cache policy
     */
    public function getPolicy(string $route, string $area, bool $cacheable, ?CacheAttribute $cacheAttribute = null, bool $enforceNoStore = false): CachePolicy
    {
        $policyModifier = $cacheAttribute?->policyModifier;

        $policyName = null;
        $isDefaultPolicy = false;

        // Priority 1: Route with modifier
        if ($policyModifier !== null && $route !== '') {
            $modifiedRouteKey = $route . '#' . $policyModifier;
            if (isset($this->routePolicies[$modifiedRouteKey])) {
                $policyName = $this->routePolicies[$modifiedRouteKey];
            }
        }

        // Priority 2: Route-level override
        if ($policyName === null && $route !== '' && isset($this->routePolicies[$route])) {
            $policyName = $this->routePolicies[$route];
        }

        // Priority 3: Area defaults
        if ($policyName === null) {
            $areaDefaults = $this->defaultPolicies[$area] ?? null;
            if ($areaDefaults !== null) {
                $policyName = $cacheable ? $areaDefaults->cacheablePolicyName : $areaDefaults->uncacheablePolicyName;
                $isDefaultPolicy = true;
            }
        }

        $policy = $this->policies[$policyName] ?? null;
        if ($policy === null) {
            return CachePolicy::noStore();
        }

        // When enforcing no-store, always return no-cache policy (merging makes no sense as other cache-control directives should be ignored in this case)
        if ($enforceNoStore && $isDefaultPolicy) {
            return CachePolicy::noStore();
        }

        // Override with CacheAttribute values if using default policy and cacheable
        if ($isDefaultPolicy && $cacheable && $cacheAttribute !== null) {
            return $this->getPolicyWithAttributeOverrides($policy, $cacheAttribute);
        }

        return $policy;
    }

    private function getPolicyWithAttributeOverrides(CachePolicy $policy, CacheAttribute $cacheAttribute): CachePolicy
    {
        $overrides = [];
        if (
            $cacheAttribute->maxAge !== null && $policy->cacheControl->maxAge !== null) { // don't allow adding max-age to a policy that doesn't have it
            $overrides['max_age'] = $cacheAttribute->maxAge;
        }
        if ($cacheAttribute->sMaxAge !== null && $policy->cacheControl->sMaxAge !== null) { // don't allow adding s-maxage to a policy that doesn't have it
            $overrides['s_maxage'] = $cacheAttribute->sMaxAge;
        }

        return $policy->with(
            cacheControl: $policy->cacheControl->with($overrides),
        );
    }
}
