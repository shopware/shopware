<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheControlDirectives;

/**
 * @internal
 */
#[CoversClass(CacheControlDirectives::class)]
class CacheControlDirectivesTest extends TestCase
{
    public function testToArray(): void
    {
        $policy = new CacheControlDirectives(
            public: true,
            maxAge: 600,
            sMaxAge: 3600,
            staleWhileRevalidate: 60,
            staleIfError: 300
        );

        $array = $policy->toArray();

        static::assertSame([
            'public' => true,
            'max_age' => 600,
            's_maxage' => 3600,
            'stale_while_revalidate' => 60,
            'stale_if_error' => 300,
        ], $array);
    }

    public function testToArrayWithAllDirectives(): void
    {
        $policy = new CacheControlDirectives(
            public: true,
            private: false,
            noCache: true,
            noStore: true,
            noTransform: true,
            mustRevalidate: true,
            proxyRevalidate: true,
            immutable: true,
            maxAge: 600,
            sMaxAge: 3600,
            staleWhileRevalidate: 60,
            staleIfError: 300
        );

        $array = $policy->toArray();

        static::assertSame([
            'public' => true,
            'private' => false,
            'no_cache' => true,
            'no_store' => true,
            'no_transform' => true,
            'must_revalidate' => true,
            'proxy_revalidate' => true,
            'immutable' => true,
            'max_age' => 600,
            's_maxage' => 3600,
            'stale_while_revalidate' => 60,
            'stale_if_error' => 300,
        ], $array);
    }

    public function testFromArray(): void
    {
        $data = [
            'public' => true,
            'no_cache' => false,
            'max_age' => 1800,
            's_maxage' => 7200,
        ];

        $policy = CacheControlDirectives::fromArray($data);

        static::assertTrue($policy->public);
        static::assertFalse($policy->noCache);
        static::assertSame(1800, $policy->maxAge);
        static::assertSame(7200, $policy->sMaxAge);
        static::assertNull($policy->private);
    }

    public function testFromArrayWithAllDirectives(): void
    {
        $data = [
            'public' => true,
            'private' => false,
            'no_cache' => true,
            'no_store' => true,
            'no_transform' => true,
            'must_revalidate' => true,
            'proxy_revalidate' => true,
            'immutable' => true,
            'max_age' => 600,
            's_maxage' => 3600,
            'stale_while_revalidate' => 60,
            'stale_if_error' => 300,
        ];

        $policy = CacheControlDirectives::fromArray($data);

        static::assertTrue($policy->public);
        static::assertFalse($policy->private);
        static::assertTrue($policy->noCache);
        static::assertTrue($policy->noStore);
        static::assertTrue($policy->noTransform);
        static::assertTrue($policy->mustRevalidate);
        static::assertTrue($policy->proxyRevalidate);
        static::assertTrue($policy->immutable);
        static::assertSame(600, $policy->maxAge);
        static::assertSame(3600, $policy->sMaxAge);
        static::assertSame(60, $policy->staleWhileRevalidate);
        static::assertSame(300, $policy->staleIfError);
    }

    public function testWith(): void
    {
        $policy = new CacheControlDirectives(
            public: true,
            maxAge: 600,
            sMaxAge: 3600
        );

        $newPolicy = $policy->with(
            [
                'public' => false,
                'max_age' => 1200,
                'no_store' => true,
            ]
        );

        static::assertFalse($newPolicy->public);
        static::assertSame(1200, $newPolicy->maxAge);
        static::assertSame(3600, $newPolicy->sMaxAge);
        static::assertTrue($newPolicy->noStore);
    }
}
