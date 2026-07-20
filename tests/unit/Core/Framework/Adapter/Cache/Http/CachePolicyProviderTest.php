<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheAttribute;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheControlDirectives;
use Shopware\Core\Framework\Adapter\Cache\Http\CachePolicy;
use Shopware\Core\Framework\Adapter\Cache\Http\CachePolicyProvider;
use Shopware\Core\Framework\Adapter\Cache\Http\DefaultPolicies;

/**
 * @internal
 */
#[CoversClass(CachePolicyProvider::class)]
class CachePolicyProviderTest extends TestCase
{
    /**
     * @param array<string, CachePolicy> $policies
     * @param array<string, string> $routePolicies
     * @param array<string, DefaultPolicies> $defaultPolicies
     */
    #[DataProvider('providePolicyResolutionCases')]
    public function testGetPolicy(
        array $policies,
        array $routePolicies,
        array $defaultPolicies,
        string $route,
        string $area,
        bool $cacheable,
        ?CacheAttribute $cacheAttribute,
        CachePolicy $expectedPolicy,
        bool $enforceNoStore = false,
    ): void {
        $provider = new CachePolicyProvider($policies, $routePolicies, $defaultPolicies);

        $result = $provider->getPolicy($route, $area, $cacheable, $cacheAttribute, $enforceNoStore);

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
     *     cacheAttribute: ?CacheAttribute,
     *     expectedPolicy: CachePolicy,
     *     enforceNoStore?: bool
     * }>
     */
    public static function providePolicyResolutionCases(): iterable
    {
        $specificPolicy = new CachePolicy(
            cacheControl: new CacheControlDirectives(
                public: true,
                maxAge: 3600
            )
        );
        $defaultPolicy = new CachePolicy(
            cacheControl: new CacheControlDirectives(
                public: true,
                maxAge: 600
            )
        );
        $hookPolicy = new CachePolicy(
            cacheControl: new CacheControlDirectives(
                public: true,
                maxAge: 7200
            )
        );
        $uncacheablePolicy = new CachePolicy(
            cacheControl: new CacheControlDirectives(
                private: true,
                noStore: true
            )
        );

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
            'cacheAttribute' => new CacheAttribute(),
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
            'cacheAttribute' => new CacheAttribute(policyModifier: 'my-hook'),
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
            'cacheAttribute' => new CacheAttribute(),
            'expectedPolicy' => $defaultPolicy,
        ];

        yield 'area cacheable default with max_age from CacheAttribute' => [
            'policies' => ['area_cacheable' => new CachePolicy(
                cacheControl: new CacheControlDirectives(public: true, maxAge: 300),
            )],
            'routePolicies' => [],
            'defaultPolicies' => [
                'storefront' => new DefaultPolicies('area_cacheable', 'no_cache'),
            ],
            'route' => 'some.route',
            'area' => 'storefront',
            'cacheable' => true,
            'cacheAttribute' => new CacheAttribute(maxAge: 1200),
            'expectedPolicy' => new CachePolicy(
                cacheControl: new CacheControlDirectives(public: true, maxAge: 1200),
            ),
        ];

        yield 'area cacheable default with s_maxage from CacheAttribute' => [
            'policies' => ['area_cacheable' => new CachePolicy(
                cacheControl: new CacheControlDirectives(public: true, sMaxAge: 300),
            )],
            'routePolicies' => [],
            'defaultPolicies' => [
                'storefront' => new DefaultPolicies('area_cacheable', 'no_cache'),
            ],
            'route' => 'some.route',
            'area' => 'storefront',
            'cacheable' => true,
            'cacheAttribute' => new CacheAttribute(sMaxAge: 1100),
            'expectedPolicy' => new CachePolicy(
                cacheControl: new CacheControlDirectives(public: true, sMaxAge: 1100)
            ),
        ];

        yield 'area cacheable default with max_age not overridden by CacheAttribute value while missing in original policy' => [
            'policies' => ['area_cacheable' => new CachePolicy(
                cacheControl: new CacheControlDirectives(public: true, sMaxAge: 300),
            )],
            'routePolicies' => [],
            'defaultPolicies' => [
                'storefront' => new DefaultPolicies('area_cacheable', 'no_cache'),
            ],
            'route' => 'some.route',
            'area' => 'storefront',
            'cacheable' => true,
            'cacheAttribute' => new CacheAttribute(maxAge: 1100, sMaxAge: 1200),
            'expectedPolicy' => new CachePolicy(
                cacheControl: new CacheControlDirectives(public: true, sMaxAge: 1200),
            ),
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
            'cacheAttribute' => new CacheAttribute(),
            'expectedPolicy' => $uncacheablePolicy,
        ];

        yield 'fallback when no policy found' => [
            'policies' => [],
            'routePolicies' => [],
            'defaultPolicies' => [],
            'route' => 'unknown.route',
            'area' => 'unknown_area',
            'cacheable' => true,
            'cacheAttribute' => new CacheAttribute(),
            'expectedPolicy' => CachePolicy::noStore(),
        ];

        yield 'enforceNoStore with default policy returns noStore policy' => [
            'policies' => ['default_policy' => $defaultPolicy],
            'routePolicies' => [],
            'defaultPolicies' => [
                'storefront' => new DefaultPolicies('default_policy', 'default_policy'),
            ],
            'route' => 'some.route',
            'area' => 'storefront',
            'cacheable' => false,
            'cacheAttribute' => null,
            'expectedPolicy' => CachePolicy::noStore(),
            'enforceNoStore' => true,
        ];

        yield 'enforceNoStore with route-specific policy returns original policy' => [
            'policies' => [
                'default_policy' => $defaultPolicy,
                'specific_policy' => $specificPolicy,
            ],
            'routePolicies' => ['my.route' => 'specific_policy'],
            'defaultPolicies' => [
                'storefront' => new DefaultPolicies('default_policy', 'default_policy'),
            ],
            'route' => 'my.route',
            'area' => 'storefront',
            'cacheable' => false,
            'cacheAttribute' => null,
            'expectedPolicy' => $specificPolicy,
            'enforceNoStore' => true,
        ];
    }
}
