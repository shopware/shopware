<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Http\CachePolicy;
use Shopware\Core\Framework\Adapter\Cache\Http\CachePolicyProviderFactory;
use Shopware\Core\Framework\Adapter\Cache\Http\DefaultPolicies;
use Shopware\Core\Framework\Adapter\Cache\Http\NoVarySearchDirectives;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-import-type CachePolicyConfig from CachePolicy
 * @phpstan-import-type DefaultPoliciesConfig from DefaultPolicies
 * @phpstan-import-type NoVarySearchDirectivesConfig from NoVarySearchDirectives
 */
#[Package('framework')]
#[CoversClass(CachePolicyProviderFactory::class)]
class CachePolicyProviderFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $policiesConfig = [
            'test_policy' => [
                'headers' => [
                    'cache_control' => [
                        'public' => true,
                        'max_age' => 600,
                    ],
                ],
            ],
        ];

        $routePoliciesConfig = ['test.route' => 'test_policy'];

        $defaultPoliciesConfig = [
            'storefront' => [
                'cacheable' => 'test_policy',
                'uncacheable' => null,
            ],
        ];

        $provider = CachePolicyProviderFactory::create(
            $policiesConfig,
            $routePoliciesConfig,
            $defaultPoliciesConfig
        );

        $policy = $provider->getPolicy('test.route', 'storefront', true, null);

        static::assertTrue($policy->cacheControl->public);
        static::assertSame(600, $policy->cacheControl->maxAge);
        static::assertNull($policy->noVarySearch);
    }

    public function testCreateWithNoVarySearch(): void
    {
        $provider = CachePolicyProviderFactory::create(
            $this->policiesConfig(['key_order' => true]),
            [],
            $this->defaultPoliciesConfig()
        );

        $policy = $provider->getPolicy('', 'storefront', true, null);

        static::assertNotNull($policy->noVarySearch);
        static::assertSame('key-order', $policy->noVarySearch->toHeaderValue());
    }

    public function testIncludeIgnoredUrlParametersIsExpanded(): void
    {
        $provider = CachePolicyProviderFactory::create(
            $this->policiesConfig(['key_order' => true, 'include_ignored_url_parameters' => true]),
            [],
            $this->defaultPoliciesConfig(),
            ['utm_source', 'gclid']
        );

        $policy = $provider->getPolicy('', 'storefront', true, null);

        static::assertNotNull($policy->noVarySearch);
        static::assertSame(['utm_source', 'gclid'], $policy->noVarySearch->params);
        static::assertSame('key-order, params=("utm_source" "gclid")', $policy->noVarySearch->toHeaderValue());
    }

    public function testIncludeIgnoredUrlParametersMergesWithExplicitParamsWithoutDuplicates(): void
    {
        $provider = CachePolicyProviderFactory::create(
            $this->policiesConfig(['params' => ['ref', 'gclid'], 'include_ignored_url_parameters' => true]),
            [],
            $this->defaultPoliciesConfig(),
            ['utm_source', 'gclid']
        );

        $policy = $provider->getPolicy('', 'storefront', true, null);

        static::assertNotNull($policy->noVarySearch);
        static::assertSame(['ref', 'gclid', 'utm_source'], $policy->noVarySearch->params);
    }

    public function testIncludeIgnoredUrlParametersDoesNotNarrowAllParams(): void
    {
        $provider = CachePolicyProviderFactory::create(
            $this->policiesConfig(['params' => true, 'include_ignored_url_parameters' => true]),
            [],
            $this->defaultPoliciesConfig(),
            ['utm_source']
        );

        $policy = $provider->getPolicy('', 'storefront', true, null);

        static::assertNotNull($policy->noVarySearch);
        static::assertTrue($policy->noVarySearch->params);
        static::assertSame('params', $policy->noVarySearch->toHeaderValue());
    }

    public function testIgnoredUrlParametersAreNotAddedWithoutOptIn(): void
    {
        $provider = CachePolicyProviderFactory::create(
            $this->policiesConfig(['key_order' => true]),
            [],
            $this->defaultPoliciesConfig(),
            ['utm_source']
        );

        $policy = $provider->getPolicy('', 'storefront', true, null);

        static::assertNotNull($policy->noVarySearch);
        static::assertNull($policy->noVarySearch->params);
    }

    /**
     * @param NoVarySearchDirectivesConfig $noVarySearch
     *
     * @return array<string, CachePolicyConfig>
     */
    private function policiesConfig(array $noVarySearch): array
    {
        return [
            'test_policy' => [
                'headers' => [
                    'cache_control' => ['public' => true],
                    'no_vary_search' => $noVarySearch,
                ],
            ],
        ];
    }

    /**
     * @return array<string, DefaultPoliciesConfig>
     */
    private function defaultPoliciesConfig(): array
    {
        return ['storefront' => ['cacheable' => 'test_policy', 'uncacheable' => null]];
    }
}
