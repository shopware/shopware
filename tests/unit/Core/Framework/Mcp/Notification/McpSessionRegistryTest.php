<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\Notification\McpSessionRegistry;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * @internal
 */
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
}
