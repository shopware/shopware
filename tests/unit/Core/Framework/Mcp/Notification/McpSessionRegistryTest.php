<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\McpSessionRegistry;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

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
}
