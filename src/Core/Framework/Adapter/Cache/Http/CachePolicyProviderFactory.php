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
     * @param list<string> $ignoredUrlParameters
     */
    public static function create(
        array $policiesConfig,
        array $routePoliciesConfig,
        array $defaultPoliciesConfig,
        array $ignoredUrlParameters = []
    ): CachePolicyProvider {
        // init CachePolicy objects from config arrays
        $policies = array_map(static function ($directives) use ($ignoredUrlParameters) {
            return CachePolicy::fromArray(self::resolveIgnoredUrlParameters($directives, $ignoredUrlParameters));
        }, $policiesConfig);

        // init DefaultPolicies objects from config arrays
        $defaultPolicies = array_map(static function ($defaults) {
            return DefaultPolicies::fromArray($defaults);
        }, $defaultPoliciesConfig);

        return new CachePolicyProvider($policies, $routePoliciesConfig, $defaultPolicies);
    }

    /**
     * Expands `no_vary_search.include_ignored_url_parameters` into the concrete `params` list.
     *
     * The globally configured `shopware.http_cache.ignored_url_parameters` are already stripped from
     * the server side cache key, so declaring them in the header only tells clients what the server
     * cache does anyway. Expanding it here keeps the directives value object free of global config.
     *
     * @param CachePolicyConfig $policyConfig
     * @param list<string> $ignoredUrlParameters
     *
     * @return CachePolicyConfig
     */
    private static function resolveIgnoredUrlParameters(array $policyConfig, array $ignoredUrlParameters): array
    {
        $directives = $policyConfig['headers']['no_vary_search'] ?? null;

        if ($directives === null || ($directives['include_ignored_url_parameters'] ?? false) !== true) {
            return $policyConfig;
        }

        unset($directives['include_ignored_url_parameters']);

        $params = $directives['params'] ?? null;

        // `params: true` already covers every parameter, there is nothing to add
        if ($params !== true) {
            $params = \is_array($params) ? $params : [];

            $directives['params'] = array_values(array_unique([...$params, ...$ignoredUrlParameters]));
        }

        $policyConfig['headers']['no_vary_search'] = $directives;

        return $policyConfig;
    }
}
