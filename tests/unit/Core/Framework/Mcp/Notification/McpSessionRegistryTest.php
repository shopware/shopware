<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\McpSessionRegistry;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpSessionRegistry::class)]
class McpSessionRegistryTest extends TestCase
{
    public function testRegistersSessionsOnlyOnce(): void
    {
        $registry = new McpSessionRegistry(new Psr16Cache(new ArrayAdapter()));

        $registry->register('session-a');
        $registry->register('session-a');
        $registry->register('session-b');

        static::assertSame(['session-a', 'session-b'], $registry->all());
    }

    public function testRemovesSessions(): void
    {
        $registry = new McpSessionRegistry(new Psr16Cache(new ArrayAdapter()));

        $registry->register('session-a');
        $registry->register('session-b');
        $registry->remove('session-a');

        static::assertSame(['session-b'], $registry->all());
    }

    public function testAllIgnoresMalformedCachedSessionIds(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $cache->set('shopware.mcp.active_session_ids', ['session-a', '', 42, 'session-b']);

        $registry = new McpSessionRegistry($cache);

        static::assertSame(['session-a', 'session-b'], $registry->all());
    }

    public function testAllReturnsEmptyListWhenCacheValueIsNotAnArray(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $cache->set('shopware.mcp.active_session_ids', 'broken');

        $registry = new McpSessionRegistry($cache);

        static::assertSame([], $registry->all());
    }

    public function testDistinctCacheKeysIsolateSessionPopulations(): void
    {
        // Both registries share one cache pool, mirroring the Admin and Store API endpoints wiring.
        $cache = new Psr16Cache(new ArrayAdapter());

        $admin = new McpSessionRegistry($cache, 'shopware.mcp.active_session_ids');
        $storeApi = new McpSessionRegistry($cache, 'shopware.mcp.store_api.active_session_ids');

        $admin->register('admin-session');
        $storeApi->register('store-api-session');

        static::assertSame(['admin-session'], $admin->all());
        static::assertSame(['store-api-session'], $storeApi->all());

        // Removing from one scope must not affect the other.
        $storeApi->remove('store-api-session');

        static::assertSame(['admin-session'], $admin->all());
        static::assertSame([], $storeApi->all());
    }

    public function testMutationsAcquireAndReleaseAScopeSpecificLock(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->exactly(2))->method('acquire')->with(true);
        $lock->expects($this->exactly(2))->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->exactly(2))
            ->method('createLock')
            ->with('mcp.session_registry.shopware.mcp.active_session_ids')
            ->willReturn($lock);

        $registry = new McpSessionRegistry(
            new Psr16Cache(new ArrayAdapter()),
            'shopware.mcp.active_session_ids',
            $lockFactory,
        );

        $registry->register('session-a');
        $registry->remove('session-a');

        static::assertSame([], $registry->all());
    }

    public function testDefaultCacheKeyIsUsedWhenNoneProvided(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());

        $registry = new McpSessionRegistry($cache);
        $registry->register('session-a');

        static::assertSame(['session-a'], $cache->get('shopware.mcp.active_session_ids'));
    }
}
