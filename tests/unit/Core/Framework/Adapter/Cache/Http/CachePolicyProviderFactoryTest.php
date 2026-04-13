<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Http\CachePolicyProviderFactory;

/**
 * @internal
 */
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
    }
}
