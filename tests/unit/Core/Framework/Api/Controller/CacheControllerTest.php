<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Api\Controller\CacheController;
use Shopware\Core\Framework\Api\Event\InvalidateExpiredCacheRequestEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Test\Stub\EventDispatcher\AssertingEventDispatcher;
use Shopware\Elasticsearch\Framework\Indexing\IndexManager;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CacheController::class)]
class CacheControllerTest extends TestCase
{
    public function testClearCache(): void
    {
        $cacheClearerMock = $this->createMock(CacheClearer::class);
        $cacheClearerMock->expects($this->once())
            ->method('clear');

        $controller = new CacheController(
            $cacheClearerMock,
            $this->createMock(CacheInvalidator::class),
            new NullAdapter(),
            $this->createMock(EntityIndexerRegistry::class),
            new EventDispatcher()
        );

        $controller->clearCache();
    }

    public function testClearDelayedCache(): void
    {
        $cacheInvalidatorMock = $this->createMock(CacheInvalidator::class);
        $cacheInvalidatorMock->expects($this->once())
            ->method('invalidateExpired');

        $indexManager = $this->createMock(IndexManager::class);
        $indexManager->expects($this->never())
            ->method('refreshIndices');

        $eventDispatcher = new AssertingEventDispatcher($this, [
            InvalidateExpiredCacheRequestEvent::class => 1,
        ]);

        $controller = new CacheController(
            $this->createMock(CacheClearer::class),
            $cacheInvalidatorMock,
            new NullAdapter(),
            $this->createMock(EntityIndexerRegistry::class),
            $eventDispatcher,
        );

        $controller->clearDelayedCache(new Request());
    }
}
