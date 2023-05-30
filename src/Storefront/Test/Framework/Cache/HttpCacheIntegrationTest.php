<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Storefront\Framework\Cache\CacheResponseSubscriber;
use Shopware\Storefront\Framework\Cache\CacheStore;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;

/**
 * @internal
 *
 * @group skip-paratest
 * @group cache
 */
class HttpCacheIntegrationTest extends TestCase
{
    use KernelTestBehaviour;
    use CacheTestBehaviour;
    use AppSystemTestBehaviour;

    private static string $originalHttpCacheValue;

    public static function setUpBeforeClass(): void
    {
        self::$originalHttpCacheValue = $_SERVER['SHOPWARE_HTTP_CACHE_ENABLED'] ?? '';
        $_ENV['SHOPWARE_HTTP_CACHE_ENABLED'] = $_SERVER['SHOPWARE_HTTP_CACHE_ENABLED'] = '1';
    }

    public static function tearDownAfterClass(): void
    {
        $_ENV['SHOPWARE_HTTP_CACHE_ENABLED'] = $_SERVER['SHOPWARE_HTTP_CACHE_ENABLED'] = self::$originalHttpCacheValue;
    }

    protected function setUp(): void
    {
        KernelLifecycleManager::bootKernel();

        $this->getContainer()
            ->get(Connection::class)
            ->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        static::assertEquals(
            1,
            $connection->getTransactionNestingLevel(),
            'Too many Nesting Levels.
            Probably one transaction was not closed properly.
            This may affect following Tests in an unpredictable manner!
            Current nesting level: "' . $connection->getTransactionNestingLevel() . '".'
        );

        $connection->rollBack();
    }

    public function testCacheHit(): void
    {
        $kernel = $this->getCacheKernel();

        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $request = $this->createRequest($appUrl);

        $response = $kernel->handle($request);
        static::assertTrue($response->headers->has('x-symfony-cache'));
        static::assertEquals('GET /: miss, store', $response->headers->get('x-symfony-cache'));

        $response = $kernel->handle($request);
        static::assertEquals('GET /: fresh', $response->headers->get('x-symfony-cache'));
    }

    public function testCacheHitWithDifferentCacheKeys(): void
    {
        $kernel = $this->getCacheKernel();

        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $request = $this->createRequest($appUrl);
        $request->cookies->set(CacheResponseSubscriber::CONTEXT_CACHE_COOKIE, 'a');

        $response = $kernel->handle($request);
        static::assertEquals('GET /: miss, store', $response->headers->get('x-symfony-cache'));

        $response = $kernel->handle($request);
        static::assertEquals('GET /: fresh', $response->headers->get('x-symfony-cache'));

        $request->cookies->set(CacheResponseSubscriber::CONTEXT_CACHE_COOKIE, 'b');

        $response = $kernel->handle($request);
        static::assertEquals('GET /: miss, store', $response->headers->get('x-symfony-cache'));
    }

    public function testCacheForAppScriptEndpointIsEnabledByDefault(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/http-cache-cases');

        $kernel = $this->getCacheKernel();

        $route = '/storefront/script/cache-default';
        $request = $this->createRequest(EnvironmentHelper::getVariable('APP_URL') . $route);

        $response = $kernel->handle($request);
        static::assertEquals(sprintf('GET %s: miss, store', $route), $response->headers->get('x-symfony-cache'));
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));

        $response = $kernel->handle($request);
        static::assertEquals(sprintf('GET %s: fresh', $route), $response->headers->get('x-symfony-cache'));
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));
    }

    public function testCacheForAppScriptEndpointOptOut(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/http-cache-cases');

        $kernel = $this->getCacheKernel();

        $route = '/storefront/script/cache-disable';
        $request = $this->createRequest(EnvironmentHelper::getVariable('APP_URL') . $route);

        $response = $kernel->handle($request);
        static::assertEquals(sprintf('GET %s: miss', $route), $response->headers->get('x-symfony-cache'));
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));

        $response = $kernel->handle($request);
        static::assertEquals(sprintf('GET %s: miss', $route), $response->headers->get('x-symfony-cache'));
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));
    }

    public function testCacheForAppScriptEndpointCustomCacheTags(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/http-cache-cases');

        $kernel = $this->getCacheKernel();

        $route = '/storefront/script/custom-cache-tags';
        $request = $this->createRequest(EnvironmentHelper::getVariable('APP_URL') . $route);

        $response = $kernel->handle($request);
        static::assertEquals(sprintf('GET %s: miss, store', $route), $response->headers->get('x-symfony-cache'));
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));

        $response = $kernel->handle($request);
        static::assertEquals(sprintf('GET %s: fresh', $route), $response->headers->get('x-symfony-cache'));
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));

        $cacheInvalidator = $this->getContainer()->get(CacheInvalidator::class);
        $cacheInvalidator->invalidate(['my-custom-tag'], true);

        $response = $kernel->handle($request);
        static::assertEquals(sprintf('GET %s: miss, store', $route), $response->headers->get('x-symfony-cache'));
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));
    }

    public function testCacheForAppScriptEndpointCustomCacheTagsWithScriptInvalidation(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/http-cache-cases');

        $kernel = $this->getCacheKernel();

        $route = '/storefront/script/custom-cache-tags';
        $request = $this->createRequest(EnvironmentHelper::getVariable('APP_URL') . $route);

        $response = $kernel->handle($request);
        static::assertEquals(sprintf('GET %s: miss, store', $route), $response->headers->get('x-symfony-cache'));
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));

        $response = $kernel->handle($request);
        static::assertEquals(sprintf('GET %s: fresh', $route), $response->headers->get('x-symfony-cache'));
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));

        $ids = new IdsCollection();
        $productRepo = $this->getContainer()->get('product.repository');
        // entity written event will execute the cache invalidation script, which will invalidate our custom tag
        $productRepo->create([
            (new ProductBuilder($ids, 'p1'))
            ->price(100)
            ->build(),
        ], Context::createDefaultContext());

        $response = $kernel->handle($request);
        static::assertEquals(sprintf('GET %s: miss, store', $route), $response->headers->get('x-symfony-cache'));
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));
    }

    public function testCacheForAppScriptEndpointCustomCacheConfig(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/http-cache-cases');

        $kernel = $this->getCacheKernel();

        $route = '/storefront/script/custom-cache-config';
        $request = $this->createRequest(EnvironmentHelper::getVariable('APP_URL') . $route);

        $response = $kernel->handle($request);
        static::assertEquals(sprintf('GET %s: miss, store', $route), $response->headers->get('x-symfony-cache'));
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));
        static::assertEquals(5, $response->getMaxAge());
        static::assertEquals('logged-in', $response->headers->get(CacheResponseSubscriber::INVALIDATION_STATES_HEADER));

        $response = $kernel->handle($request);
        static::assertEquals(sprintf('GET %s: fresh', $route), $response->headers->get('x-symfony-cache'));
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));
        static::assertEquals(5, $response->getMaxAge());
        static::assertEquals('logged-in', $response->headers->get(CacheResponseSubscriber::INVALIDATION_STATES_HEADER));
    }

    private function createRequest(?string $url = null): Request
    {
        if ($url === null) {
            $url = $this->getContainer()->get(Connection::class)->fetchOne('SELECT url FROM sales_channel_domain LIMIT 1');
        }

        $request = Request::create($url);

        // resolves seo urls and detects storefront sales channels
        return $this->getContainer()
            ->get(RequestTransformerInterface::class)
            ->transform($request);
    }

    private function getCacheKernel(): HttpCache
    {
        $store = $this->getContainer()->get(CacheStore::class);

        return new HttpCache($this->getKernel(), $store, null, ['debug' => true]);
    }
}
