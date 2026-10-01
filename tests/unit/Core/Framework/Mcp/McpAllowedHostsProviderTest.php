<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpAllowedHostsProvider;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpAllowedHostsProvider::class)]
class McpAllowedHostsProviderTest extends TestCase
{
    public function testAlwaysAllowsLocalhostVariants(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);

        $provider = new McpAllowedHostsProvider($connection, 'http://localhost');

        static::assertSame(['localhost', '127.0.0.1', '[::1]'], $provider->getAllowedHosts());
    }

    public function testIncludesAppUrlAndSalesChannelDomainHostsWithoutPort(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([
            'http://trunk.localhost:8088',
            'https://shop.example.com',
            'https://de.example.com/de',
        ]);

        $provider = new McpAllowedHostsProvider($connection, 'http://trunk.localhost:8088');

        static::assertSame(
            ['localhost', '127.0.0.1', '[::1]', 'trunk.localhost', 'shop.example.com', 'de.example.com'],
            $provider->getAllowedHosts(),
        );
    }

    public function testLowercasesAndDeduplicatesHosts(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([
            'https://Shop.Example.com',
            'https://shop.example.com:8443',
        ]);

        $provider = new McpAllowedHostsProvider($connection, 'https://SHOP.example.com');

        static::assertSame(['localhost', '127.0.0.1', '[::1]', 'shop.example.com'], $provider->getAllowedHosts());
    }

    public function testSkipsDomainsWithoutParseableHost(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([
            'default.headless0',
            '',
            'https://valid.example.com',
        ]);

        $provider = new McpAllowedHostsProvider($connection, 'http://localhost');

        static::assertSame(['localhost', '127.0.0.1', '[::1]', 'valid.example.com'], $provider->getAllowedHosts());
    }
}
