<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IterateEntityIndexerMessage;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group skip-paratest
 */
class CacheControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @var TagAwareAdapterInterface
     */
    private $cache;

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
        $this->getContainer()->get('messenger.bus.shopware')->reset();

        $this->getBrowser()->request(
            'POST',
            '/api/_action/index',
            [],
            [],
            [
                'HTTP_CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['skip' => ['category.indexer']])
        );

        /** @var JsonResponse $response */
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), print_r($response->getContent(), true));

        $messages = $this->getContainer()->get('messenger.bus.shopware')->getDispatchedMessages();

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

            static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $response->getContent());
            static::assertEquals(MissingPrivilegeException::MISSING_PRIVILEGE_ERROR, json_decode($response->getContent(), true)['errors'][0]['code'], $response->getContent());
        } finally {
            $this->resetBrowser();
        }
    }
}
