<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Http {
    use PHPUnit\Framework\Attributes\CoversClass;
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
    use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
    use Shopware\Core\Framework\Adapter\Cache\Http\CacheStateValidator;
    use Shopware\Core\Framework\Adapter\Cache\Http\CacheStore;
    use Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
    use Shopware\Core\Framework\Routing\MaintenanceModeResolver;
    use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
    use Symfony\Component\Cache\Adapter\ArrayAdapter;
    use Symfony\Component\Cache\Adapter\TagAwareAdapter;
    use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
    use Symfony\Component\Cache\CacheItem;
    use Symfony\Component\EventDispatcher\EventDispatcher;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\HttpKernelInterface;
    use Symfony\Component\Lock\LockFactory;
    use Symfony\Component\Lock\Store\InMemoryStore;

    /**
     * @internal
     */
    #[CoversClass(CacheStore::class)]
    class CacheStoreTest extends TestCase
    {
        public function testGetLock(): void
        {
            $request = new Request();

            $cache = $this->createMock(TagAwareAdapterInterface::class);

            $cache->expects($this->once())->method('hasItem')->willReturn(false);

            $item = new CacheItem();

            $cache->expects($this->once())->method('getItem')->willReturn($item);

            $cache->expects($this->once())->method('save')->with($item);

            $store = new CacheStore(
                $cache,
                $this->createMock(CacheStateValidator::class),
                new EventDispatcher(),
                new HttpCacheKeyGenerator('test', new EventDispatcher(), []),
                $this->createMock(MaintenanceModeResolver::class),
                [],
                $this->createMock(CacheTagCollector::class),
                $this->createMock(HttpKernelInterface::class),
                false,
                new LockFactory(new InMemoryStore())
            );

            $store->lock($request);

            static::assertTrue($item->get());

            $value = ReflectionHelper::getPropertyValue($item, 'expiry');

            static::assertEqualsWithDelta(time() + 3, $value, 1);
        }

        public function testWriteDoesNotWriteCacheIfCacheStateIsInvalid(): void
        {
            $request = new Request();
            $response = new Response();

            $cache = $this->createMock(TagAwareAdapterInterface::class);
            $cache->expects($this->never())->method('save');

            $stateValidator = $this->createMock(CacheStateValidator::class);
            $stateValidator->expects($this->once())->method('isValid')->with($request)->willReturn(false);

            $store = new CacheStore(
                $cache,
                $stateValidator,
                new EventDispatcher(),
                new HttpCacheKeyGenerator('test', new EventDispatcher(), []),
                $this->createMock(MaintenanceModeResolver::class),
                [],
                $this->createMock(CacheTagCollector::class),
                $this->createMock(HttpKernelInterface::class),
                false,
                new LockFactory(new InMemoryStore())
            );

            $store->write($request, $response);
        }

        public function testWriteWithSoftPurgeEnabled(): void
        {
            $request = new Request();
            $response = new Response();
            $response->headers->set('date', date('Y-m-d H:i:s'));

            $cache = new TagAwareAdapter(new ArrayAdapter());

            $stateValidator = $this->createMock(CacheStateValidator::class);
            $stateValidator->expects($this->once())->method('isValid')->willReturn(true);

            $collector = $this->createMock(CacheTagCollector::class);
            $collector->expects($this->once())->method('get')->willReturn(['tag1', 'tag2']);

            $maintenanceResolver = $this->createMock(MaintenanceModeResolver::class);
            $maintenanceResolver->expects($this->once())->method('isMaintenanceRequest')->willReturn(false);

            $store = new CacheStore(
                $cache,
                $stateValidator,
                new EventDispatcher(),
                new HttpCacheKeyGenerator('test', new EventDispatcher(), []),
                $maintenanceResolver,
                [],
                $collector,
                $this->createMock(HttpKernelInterface::class),
                true,
                new LockFactory(new InMemoryStore())
            );

            $key = $store->write($request, $response);

            static::assertIsString($key);

            // Verify the cache item was stored correctly
            $cacheItem = $cache->getItem($key);
            static::assertTrue($cacheItem->isHit());

            $cacheData = CacheCompressor::uncompress($cacheItem);
            static::assertIsArray($cacheData);
            static::assertArrayHasKey('response', $cacheData);
            static::assertArrayHasKey('tags', $cacheData);
            static::assertIsArray($cacheData['tags']);
            static::assertSame(['tag1', 'tag2'], $cacheData['tags']);
        }

        public function testWriteWithSoftPurgeDisabled(): void
        {
            $request = new Request();
            $response = new Response();
            $response->headers->set('date', date('Y-m-d H:i:s'));

            $cache = new TagAwareAdapter(new ArrayAdapter());

            $stateValidator = $this->createMock(CacheStateValidator::class);
            $stateValidator->expects($this->once())->method('isValid')->willReturn(true);

            $collector = $this->createMock(CacheTagCollector::class);
            $collector->expects($this->once())->method('get')->willReturn(['tag1', 'tag2']);

            $maintenanceResolver = $this->createMock(MaintenanceModeResolver::class);
            $maintenanceResolver->expects($this->once())->method('isMaintenanceRequest')->willReturn(false);

            $store = new CacheStore(
                $cache,
                $stateValidator,
                new EventDispatcher(),
                new HttpCacheKeyGenerator('test', new EventDispatcher(), []),
                $maintenanceResolver,
                [],
                $collector,
                $this->createMock(HttpKernelInterface::class),
                false,
                new LockFactory(new InMemoryStore())
            );

            $key = $store->write($request, $response);

            static::assertIsString($key);

            // Verify the cache item was stored correctly
            $cacheItem = $cache->getItem($key);
            static::assertTrue($cacheItem->isHit());

            $cacheData = CacheCompressor::uncompress($cacheItem);
            static::assertInstanceOf(Response::class, $cacheData);
        }

        public function testLookupWithSoftPurgeAndStaleCache(): void
        {
            $request = new Request();
            $response = new Response();
            $response->headers->set('date', date('Y-m-d H:i:s', time() - 3600)); // 1 hour ago

            $cache = new TagAwareAdapter(new ArrayAdapter());

            // Pre-populate cache with response data and invalidation timestamp
            $keyGenerator = new HttpCacheKeyGenerator('test', new EventDispatcher(), []);
            $cacheKey = $keyGenerator->generate($request);

            $cacheItem = $cache->getItem($cacheKey);
            $cacheItem = CacheCompressor::compress($cacheItem, ['response' => $response, 'tags' => ['tag1']]);
            $cache->save($cacheItem);

            // Add invalidation timestamp that's newer than the response (making it stale)
            $invalidationKey = 'http_invalidation_tag1_timestamp';
            $invalidationItem = $cache->getItem($invalidationKey);
            $invalidationItem->set(time() - 1800); // 30 minutes ago, newer than response
            $cache->save($invalidationItem);

            $stateValidator = $this->createMock(CacheStateValidator::class);
            $stateValidator->expects($this->atLeastOnce())->method('isValid')->willReturn(true);

            $maintenanceResolver = $this->createMock(MaintenanceModeResolver::class);
            $maintenanceResolver->expects($this->once())->method('shouldBeCached')->willReturn(true);

            $kernel = $this->createMock(HttpKernelInterface::class);
            $kernel->expects($this->once())->method('handle')->willReturn($response);

            $store = new CacheStore(
                $cache,
                $stateValidator,
                new EventDispatcher(),
                $keyGenerator,
                $maintenanceResolver,
                [],
                $this->createMock(CacheTagCollector::class),
                $kernel,
                true,
                new LockFactory(new InMemoryStore())
            );

            $result = $store->lookup($request);

            static::assertInstanceOf(Response::class, $result);
        }

        public function testLookupWithSoftPurgeAndFreshCache(): void
        {
            $request = new Request();
            $response = new Response();
            $response->headers->set('date', date('Y-m-d H:i:s'));

            $cache = new TagAwareAdapter(new ArrayAdapter());

            // Pre-populate cache with response data and invalidation timestamp
            $keyGenerator = new HttpCacheKeyGenerator('test', new EventDispatcher(), []);
            $cacheKey = $keyGenerator->generate($request);

            $cacheItem = $cache->getItem($cacheKey);
            $cacheItem = CacheCompressor::compress($cacheItem, ['response' => $response, 'tags' => ['tag1']]);
            $cache->save($cacheItem);

            // Add invalidation timestamp that's older than the response (making it fresh)
            $invalidationKey = 'http_invalidation_tag1_timestamp';
            $invalidationItem = $cache->getItem($invalidationKey);
            $invalidationItem->set(time() - 3600); // 1 hour ago, older than response
            $cache->save($invalidationItem);

            $stateValidator = $this->createMock(CacheStateValidator::class);
            $stateValidator->expects($this->once())->method('isValid')->willReturn(true);

            $maintenanceResolver = $this->createMock(MaintenanceModeResolver::class);
            $maintenanceResolver->expects($this->once())->method('shouldBeCached')->willReturn(true);

            $kernel = $this->createMock(HttpKernelInterface::class);
            $kernel->expects($this->never())->method('handle');

            $store = new CacheStore(
                $cache,
                $stateValidator,
                new EventDispatcher(),
                $keyGenerator,
                $maintenanceResolver,
                [],
                $this->createMock(CacheTagCollector::class),
                $kernel,
                true,
                new LockFactory(new InMemoryStore())
            );

            $result = $store->lookup($request);

            static::assertInstanceOf(Response::class, $result);
        }
    }
}

namespace Shopware\Core\Framework\Adapter\Cache\Http {
    function register_shutdown_function(\Closure $function): void
    {
        $function();
    }
}
