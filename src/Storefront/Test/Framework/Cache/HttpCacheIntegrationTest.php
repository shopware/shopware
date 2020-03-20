<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Storefront\Framework\Cache\CacheResponseSubscriber;
use Shopware\Storefront\Framework\Cache\CacheStore;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;

class HttpCacheIntegrationTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use CacheTestBehaviour;

    public function testCacheHit(): void
    {
        $kernel = $this->getCacheKernel();

        $request = $this->createRequest();

        $response = $kernel->handle($request);
        static::assertTrue($response->headers->has('x-symfony-cache'));
        static::assertEquals('GET /: miss, store', $response->headers->get('x-symfony-cache'));

        $response = $kernel->handle($request);
        static::assertEquals('GET /: fresh', $response->headers->get('x-symfony-cache'));
    }

    public function testCacheHitWithDifferentCacheKeys(): void
    {
        $kernel = $this->getCacheKernel();

        $request = $this->createRequest();
        $request->cookies->set(CacheResponseSubscriber::CONTEXT_CACHE_COOKIE, 'a');

        $response = $kernel->handle($request);
        static::assertEquals('GET /: miss, store', $response->headers->get('x-symfony-cache'));

        $response = $kernel->handle($request);
        static::assertEquals('GET /: fresh', $response->headers->get('x-symfony-cache'));

        $request->cookies->set(CacheResponseSubscriber::CONTEXT_CACHE_COOKIE, 'b');

        $response = $kernel->handle($request);
        static::assertEquals('GET /: miss, store', $response->headers->get('x-symfony-cache'));
    }

    public function testInvalidation(): void
    {
        $kernel = $this->getCacheKernel();

        $request = $this->createRequest();

        $kernel->handle($request);
        $response = $kernel->handle($request);
        static::assertEquals('GET /: fresh', $response->headers->get('x-symfony-cache'));

        $navigationId = $this->getContainer()->get(Connection::class)
            ->fetchColumn('SELECT LOWER(HEX(navigation_category_id)) FROM sales_channel WHERE id = (SELECT sales_channel_id FROM sales_channel_domain LIMIT 1) ');

        $repository = $this->getContainer()->get('category.repository');

        // update category > cache should be invalidated
        $repository->update([
            ['id' => $navigationId, 'name' => 'test'],
        ], Context::createDefaultContext());

        $response = $kernel->handle($request);
        static::assertEquals('GET /: miss, store', $response->headers->get('x-symfony-cache'));
    }

    private function createRequest(?string $url = null)
    {
        if ($url === null) {
            $url = $this->getContainer()->get(Connection::class)->fetchColumn('SELECT url FROM sales_channel_domain LIMIT 1');
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
