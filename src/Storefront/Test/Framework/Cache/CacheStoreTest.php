<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Cache\CacheStateValidator;
use Shopware\Storefront\Framework\Cache\CacheStore;
use Shopware\Storefront\Framework\Cache\CacheTagCollection;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheGenerateKeyEvent;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

class CacheStoreTest extends TestCase
{
    public function testGenerateKeyEvent(): void
    {
        $cache = $this->createMock(TagAwareAdapterInterface::class);

        $eventDispatcher = new EventDispatcher();
        $cacheStore = new CacheStore(
            'test',
            $cache,
            $this->createMock(CacheStateValidator::class),
            $eventDispatcher,
            new CacheTagCollection()
        );

        $request = new Request([], ['data' => Uuid::randomHex()]);
        $expectedHash = sha1($request->request->get('data'));

        $eventDispatcher->addListener(HttpCacheGenerateKeyEvent::class, function ($event) use ($request): void {
            $this->assertInstanceOf(HttpCacheGenerateKeyEvent::class, $event);
            $this->assertSame($request, $event->getRequest());
            $event->setHash(sha1($request->request->get('data')));
        });

        $cache->expects(static::once())
            ->method('deleteItem')
            ->with($expectedHash);

        $cacheStore->invalidate($request);
    }
}
