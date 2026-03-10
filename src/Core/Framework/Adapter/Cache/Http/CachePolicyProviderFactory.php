<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Log\Package;

/**
 * Factory to create CachePolicyProvider from configuration arrays
 *
 * @internal
 *
 * @phpstan-import-type CachePolicyConfig from CachePolicy
 * @phpstan-import-type DefaultPoliciesConfig from DefaultPolicies
 */
#[Package('framework')]
class CachePolicyProviderFactory
{
    /**
     * @param array<string, CachePolicyConfig> $policiesConfig
     * @param array<string, string> $routePoliciesConfig
     * @param array<string, DefaultPoliciesConfig> $defaultPoliciesConfig
     */
    public static function create(
        array $policiesConfig,
        array $routePoliciesConfig,
        array $defaultPoliciesConfig
    ): CachePolicyProvider {
        // init CachePolicy objects from config arrays
        $policies = array_map(function ($directives) {
            return CachePolicy::fromArray($directives);
        }, $policiesConfig);

        // init DefaultPolicies objects from config arrays
        $defaultPolicies = array_map(function ($defaults) {
            return DefaultPolicies::fromArray($defaults);
        }, $defaultPoliciesConfig);

        return new CachePolicyProvider($policies, $routePoliciesConfig, $defaultPolicies);
    }
}
