<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IterateEntityIndexerMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Storefront\Framework\Cache\CacheWarmer\CacheWarmer;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\TraceableMessageBus;

/**
 * @internal
 *
 * @group skip-paratest
 */
#[Package('system-settings')]
class CacheControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private TagAwareAdapterInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = $this->getContainer()->get('cache.object');
    }

    /**
     * @group slow
     */
    public function testClearCacheEndpoint(): void
    {
        $this->cache = $this->getContainer()->get('cache.object');

        $item = $this->cache->getItem('foo');
        $item->set('bar');
        $item->tag(['foo-tag']);
        $this->cache->save($item);

        $item = $this->cache->getItem('bar');
        $item->set('foo');
        $item->tag(['bar-tag']);
        $this->cache->save($item);

        static::assertTrue($this->cache->getItem('foo')->isHit());
        static::assertTrue($this->cache->getItem('bar')->isHit());

        $this->getBrowser()->request('DELETE', '/api/_action/cache');

        /** @var JsonResponse $response */
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), print_r($response->getContent(), true));

        static::assertFalse($this->cache->getItem('foo')->isHit());
        static::assertFalse($this->cache->getItem('bar')->isHit());
    }

    public function testWarmupCacheEndpoint(): void
    {
        if (!$this->getContainer()->has(CacheWarmer::class)) {
            static::markTestSkipped('CacheWarmer test needs storefront bundle to be installed');
        }

        $this->cache = $this->getContainer()->get('cache.object');

        $item = $this->cache->getItem('foo');
        $item->set('bar');
        $item->tag(['foo-tag']);
        $this->cache->save($item);

        $item = $this->cache->getItem('bar');
        $item->set('foo');
        $item->tag(['bar-tag']);
        $this->cache->save($item);

        static::assertTrue($this->cache->getItem('foo')->isHit());
        static::assertTrue($this->cache->getItem('bar')->isHit());

        $this->getBrowser()->request('DELETE', '/api/_action/cache_warmup');

        /** @var JsonResponse $response */
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), print_r($response->getContent(), true));

        static::assertTrue($this->cache->getItem('foo')->isHit());
        static::assertTrue($this->cache->getItem('bar')->isHit());
    }

    public function testCacheInfoEndpoint(): void
    {
        $this->getBrowser()->request('GET', '/api/_action/cache_info');

        /** @var JsonResponse $response */
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($response->getContent(), true));
        static::assertSame('{"environment":"test","httpCache":false,"cacheAdapter":"CacheDecorator"}', $response->getContent());
    }

    public function testCacheIndexEndpoint(): void
    {
        $this->getBrowser()->request('POST', '/api/_action/index');

        /** @var JsonResponse $response */
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), print_r($response->getContent(), true));
    }

    public function testCacheIndexEndpointWithSkipParameter(): void
    {
        /** @var TraceableMessageBus $bus */
        $bus = $this->getContainer()->get('messenger.bus.shopware');
        $bus->reset();

        $this->getBrowser()->request(
            'POST',
            '/api/_action/index',
            [],
            [],
            [
                'HTTP_CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['skip' => ['category.indexer']], \JSON_THROW_ON_ERROR)
        );

        /** @var JsonResponse $response */
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), print_r($response->getContent(), true));

        $messages = $bus->getDispatchedMessages();

        $hasSalesChannelIndexerMessage = false;
        $hasCategoryIndexerMessage = false;
        foreach ($messages as $message) {
            if (isset($message['message']) && $message['message'] instanceof IterateEntityIndexerMessage) {
                $hasSalesChannelIndexerMessage = $hasSalesChannelIndexerMessage ?: $message['message']->getIndexer() === 'sales_channel.indexer';
                $hasCategoryIndexerMessage = $hasCategoryIndexerMessage ?: $message['message']->getIndexer() === 'category.indexer';
            }
        }

        static::assertTrue($hasSalesChannelIndexerMessage);
        static::assertFalse($hasCategoryIndexerMessage);
    }

    public function testCacheIndexEndpointNoPermissions(): void
    {
        try {
            $this->authorizeBrowser($this->getBrowser(), [], ['something']);
            $this->getBrowser()->request('POST', '/api/_action/index');

            /** @var JsonResponse $response */
            $response = $this->getBrowser()->getResponse();

            static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
            $decode = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            static::assertEquals(MissingPrivilegeException::MISSING_PRIVILEGE_ERROR, $decode['errors'][0]['code'], (string) $response->getContent());
        } finally {
            $this->resetBrowser();
        }
    }
}
