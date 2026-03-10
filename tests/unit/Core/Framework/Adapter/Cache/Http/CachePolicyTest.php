<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheControlDirectives;
use Shopware\Core\Framework\Adapter\Cache\Http\CachePolicy;

/**
 * @internal
 */
#[CoversClass(CachePolicy::class)]
class CachePolicyTest extends TestCase
{
    public function testFromArray(): void
    {
        $data = [
            'headers' => [
                'cache_control' => [
                    'public' => true,
                    'no_cache' => false,
                    'max_age' => 1800,
                    's_maxage' => 7200,
                ],
            ],
        ];

        $policy = CachePolicy::fromArray($data);

        static::assertTrue($policy->cacheControl->public);
        static::assertFalse($policy->cacheControl->noCache);
        static::assertSame(1800, $policy->cacheControl->maxAge);
        static::assertSame(7200, $policy->cacheControl->sMaxAge);
        static::assertNull($policy->cacheControl->private);
    }

    public function testFromArrayThrowsException(): void
    {
        self::expectExceptionObject(AdapterException::invalidCachePolicyConfiguration('missing required "headers.cache_control" configuration'));
        /** @phpstan-ignore argument.type (testing a wrong array shape here) */
        CachePolicy::fromArray([
            'headers' => [],
        ]);
    }

    public function testWith(): void
    {
        $policy = new CachePolicy(
            cacheControl: new CacheControlDirectives(
                public: false,
                noStore: true,
            )
        );

        $cacheControl = new CacheControlDirectives(
            public: true,
            maxAge: 600,
        );

        $newPolicy = $policy->with(
            cacheControl: $cacheControl,
        );

        static::assertSame($cacheControl, $newPolicy->cacheControl);
    }

    public function testNoStore(): void
    {
        $policy = CachePolicy::noStore();
        static::assertTrue($policy->cacheControl->noStore);
        static::assertTrue($policy->cacheControl->noCache);
        static::assertTrue($policy->cacheControl->mustRevalidate);
        static::assertSame(0, $policy->cacheControl->maxAge);
        static::assertNull($policy->cacheControl->public);
        static::assertNull($policy->cacheControl->private);
        static::assertNull($policy->cacheControl->sMaxAge); // in other case symfony will set response as public
    }
}
