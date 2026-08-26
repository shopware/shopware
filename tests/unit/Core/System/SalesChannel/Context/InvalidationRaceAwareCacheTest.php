<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\InvalidationRaceAwareCache;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InvalidationRaceAwareCache::class)]
class InvalidationRaceAwareCacheTest extends TestCase
{
    public function testCachesValueWhenNoInvalidationOccursDuringCreation(): void
    {
        $cache = new InvalidationRaceAwareCache(new TagAwareAdapter(new ArrayAdapter()));
        $calls = 0;

        static::assertSame('value', $cache->get('key', ['tag'], static function () use (&$calls): string {
            ++$calls;

            return 'value';
        }));
        static::assertSame('value', $cache->get('key', ['tag'], static function () use (&$calls): string {
            ++$calls;

            return 'different value';
        }));
        static::assertSame(1, $calls);
    }

    public function testDoesNotCacheValueWhenInvalidatedDuringCreation(): void
    {
        $adapter = new TagAwareAdapter(new ArrayAdapter());
        $cache = new InvalidationRaceAwareCache($adapter);
        $calls = 0;

        static::assertSame('first value', $cache->get('key', ['tag'], function () use ($adapter, &$calls): string {
            ++$calls;
            $adapter->invalidateTags(['tag']);

            return 'first value';
        }));
        static::assertSame('second value', $cache->get('key', ['tag'], static function () use (&$calls): string {
            ++$calls;

            return 'second value';
        }));
        static::assertSame(2, $calls);
    }
}
