<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Storefront\Framework\Cache\CacheStateValidator;
use Shopware\Storefront\Framework\Cache\CacheStore;
use Shopware\Storefront\Framework\Cache\HttpCacheKeyGenerator;
use Shopware\Storefront\Framework\Routing\MaintenanceModeResolver;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Framework\Cache\CacheStore
 */
class CacheStoreTest extends TestCase
{
    public function testGetLock(): void
    {
        $request = new Request();

        $cache = $this->createMock(TagAwareAdapterInterface::class);

        $cache->expects(static::once())->method('hasItem')->willReturn(false);

        $item = new CacheItem();

        $cache->expects(static::once())->method('getItem')->willReturn($item);

        $cache->expects(static::once())->method('save')->with($item);

        $store = new CacheStore(
            $cache,
            $this->createMock(CacheStateValidator::class),
            new EventDispatcher(),
            $this->createMock(AbstractCacheTracer::class),
            new HttpCacheKeyGenerator('test', new EventDispatcher(), []),
            $this->createMock(MaintenanceModeResolver::class),
            []
        );

        $store->lock($request);

        static::assertTrue($item->get());

        $reflectionClass = new \ReflectionClass($item);
        $prop = $reflectionClass->getProperty('expiry');
        $prop->setAccessible(true);

        static::assertEqualsWithDelta(time() + 3, $prop->getValue($item), 1);
    }
}
