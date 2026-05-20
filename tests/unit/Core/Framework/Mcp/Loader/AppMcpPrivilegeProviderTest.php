<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Loader;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPrivilegeProvider;

/**
 * @internal
 */
#[CoversClass(AppMcpPrivilegeProvider::class)]
class AppMcpPrivilegeProviderTest extends TestCase
{
    public function testReturnsEmptyMapWhenNoRows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $provider = new AppMcpPrivilegeProvider($connection);

        static::assertSame([], $provider->getAppToolPrivileges());
    }

    public function testDecodesJsonPrivilegesIntoMap(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            [
                'tool_name' => 'my-erp-sync-orders',
                'required_privileges' => '["order:read","order:update"]',
            ],
            [
                'tool_name' => 'my-erp-erp-status',
                'required_privileges' => '["system:read"]',
            ],
        ]);

        $provider = new AppMcpPrivilegeProvider($connection);

        static::assertSame(
            [
                'my-erp-sync-orders' => ['order:read', 'order:update'],
                'my-erp-erp-status' => ['system:read'],
            ],
            $provider->getAppToolPrivileges(),
        );
    }

    public function testSkipsRowsWithInvalidJson(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['tool_name' => 'broken-tool', 'required_privileges' => 'not-json'],
            ['tool_name' => 'good-tool', 'required_privileges' => '["entity:read"]'],
            ['tool_name' => 'scalar-json', 'required_privileges' => '"plain-string"'],
        ]);

        $provider = new AppMcpPrivilegeProvider($connection);

        static::assertSame(
            ['good-tool' => ['entity:read']],
            $provider->getAppToolPrivileges(),
        );
    }

    public function testReturnsEmptyMapAndLogsErrorWhenDbThrows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willThrowException(new \RuntimeException('DB down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to load app MCP tool privileges', static::arrayHasKey('exception'));

        $provider = new AppMcpPrivilegeProvider($connection, $logger);

        static::assertSame([], $provider->getAppToolPrivileges());
    }

    public function testReindexesNumericArrays(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['tool_name' => 'tool', 'required_privileges' => '{"0":"a","1":"b"}'],
        ]);

        $provider = new AppMcpPrivilegeProvider($connection);

        static::assertSame(['tool' => ['a', 'b']], $provider->getAppToolPrivileges());
    }
}
