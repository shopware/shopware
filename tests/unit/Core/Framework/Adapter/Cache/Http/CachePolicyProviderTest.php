<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Http\CachePolicy;
use Shopware\Core\Framework\Adapter\Cache\Http\CachePolicyProvider;
use Shopware\Core\Framework\Adapter\Cache\Http\DefaultPolicies;

/**
 * @internal
 *
 * @phpstan-import-type CacheAttribute from \Shopware\Core\PlatformRequest
 */
#[CoversClass(CachePolicyProvider::class)]
class CachePolicyProviderTest extends TestCase
{
    /**
     * @param array<string, CachePolicy> $policies
     * @param array<string, string> $routePolicies
     * @param array<string, DefaultPolicies> $defaultPolicies
     * @param CacheAttribute $cacheAttribute
     */
    #[DataProvider('providePolicyResolutionCases')]
    public function testGetPolicy(
        array $policies,
        array $routePolicies,
        array $defaultPolicies,
        string $route,
        string $area,
        bool $cacheable,
        array|bool|null $cacheAttribute,
        CachePolicy $expectedPolicy,
    ): void {
        $provider = new CachePolicyProvider($policies, $routePolicies, $defaultPolicies);

        $result = $provider->getPolicy($route, $area, $cacheable, $cacheAttribute);

        static::assertEquals($expectedPolicy, $result);
    }

    /**
     * @return iterable<string, array{
     *     policies: array<string, CachePolicy>,
     *     routePolicies: array<string, string>,
     *     defaultPolicies: array<string, DefaultPolicies>,
     *     route: string,
     *     area: string,
     *     cacheable: bool,
     *     cacheAttribute: CacheAttribute,
     *     expectedPolicy: CachePolicy
     * }>
     */
    public static function providePolicyResolutionCases(): iterable
    {
        $specificPolicy = new CachePolicy(public: true, maxAge: 3600);
        $defaultPolicy = new CachePolicy(public: true, maxAge: 600);
        $hookPolicy = new CachePolicy(public: true, maxAge: 7200);
        $uncacheablePolicy = new CachePolicy(private: true, noStore: true);

        yield 'route override takes precedence' => [
            'policies' => [
                'specific_policy' => $specificPolicy,
                'default_policy' => $defaultPolicy,
            ],
            'routePolicies' => ['my.route' => 'specific_policy'],
            'defaultPolicies' => [
                'store_api' => new DefaultPolicies('default_policy', 'no_cache'),
            ],
            'route' => 'my.route',
            'area' => 'store_api',
            'cacheable' => true,
            'cacheAttribute' => true,
            'expectedPolicy' => $specificPolicy,
        ];

        yield 'policy modifier appended to route name' => [
            'policies' => [
                'hook_policy' => $hookPolicy,
                'default_policy' => $defaultPolicy,
            ],
            'routePolicies' => ['frontend.script_endpoint#my-hook' => 'hook_policy'],
            'defaultPolicies' => [
                'storefront' => new DefaultPolicies('default_policy', 'no_cache'),
            ],
            'route' => 'frontend.script_endpoint',
            'area' => 'storefront',
            'cacheable' => true,
            'cacheAttribute' => ['policyModifier' => 'my-hook'],
            'expectedPolicy' => $hookPolicy,
        ];

        yield 'area cacheable default when no route policy' => [
            'policies' => ['area_cacheable' => $defaultPolicy],
            'routePolicies' => [],
            'defaultPolicies' => [
                'storefront' => new DefaultPolicies('area_cacheable', 'no_cache'),
            ],
            'route' => 'some.route',
            'area' => 'storefront',
            'cacheable' => true,
            'cacheAttribute' => true,
            'expectedPolicy' => $defaultPolicy,
        ];

        yield 'area uncacheable default' => [
            'policies' => ['uncacheable_policy' => $uncacheablePolicy],
            'routePolicies' => [],
            'defaultPolicies' => [
                'store_api' => new DefaultPolicies('cacheable_policy', 'uncacheable_policy'),
            ],
            'route' => 'some.route',
            'area' => 'store_api',
            'cacheable' => false,
            'cacheAttribute' => true,
            'expectedPolicy' => $uncacheablePolicy,
        ];

        yield 'fallback when no policy found' => [
            'policies' => [],
            'routePolicies' => [],
            'defaultPolicies' => [],
            'route' => 'unknown.route',
            'area' => 'unknown_area',
            'cacheable' => true,
            'cacheAttribute' => true,
            'expectedPolicy' => CachePolicy::noCache(),
        ];
    }
}
