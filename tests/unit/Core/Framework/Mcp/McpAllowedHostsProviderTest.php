<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return iterable<string, array{string, string, list<string>, bool}>
     */
    public static function originProvider(): iterable
    {
        yield 'the APP_URL origin' => ['https://shop.example', 'shop.example', [], true];
        yield 'the APP_URL origin with its default port' => ['https://shop.example:443', 'shop.example', [], true];
        yield 'a sales channel domain origin with its port' => ['http://trunk.localhost:8088', 'shop.example', [], true];
        yield 'a sales channel domain on another port' => ['http://trunk.localhost:9000', 'shop.example', [], false];
        yield 'a sales channel domain with another scheme' => ['http://shop.example', 'shop.example', [], false];
        yield 'a foreign origin' => ['https://attacker.example', 'shop.example', [], false];
        yield 'the opaque origin' => ['null', 'shop.example', ['http://localhost:*'], false];
        yield 'a non-http origin' => ['chrome-extension://abcdef', 'shop.example', [], false];
        yield 'a loopback origin for a loopback request' => ['http://127.0.0.1:6274', 'localhost:8000', [], true];
        yield 'an IPv6 loopback origin for a .localhost request' => ['http://[::1]:6274', 'trunk.localhost:8088', [], true];
        yield 'a loopback origin for a public request' => ['http://localhost:6274', 'shop.example', [], false];
        yield 'another 127.0.0.0/8 origin for a loopback request' => ['http://127.1.2.3:6274', 'localhost:8000', [], true];
        yield 'a loopback origin for a 127.0.0.0/8 request' => ['http://localhost:6274', '127.0.0.1:8000', [], true];
        yield 'a hostname starting with 127. for a loopback request' => ['http://127.attacker.example', 'localhost:8000', [], false];
        yield 'a hostname starting with 127.0.0.1. for a loopback request' => ['http://127.0.0.1.attacker.example', 'localhost:8000', [], false];
        yield 'a loopback origin for a request host starting with 127.' => ['http://localhost:6274', '127.attacker.example', [], false];
        yield 'a non-loopback IPv4 origin for a loopback request' => ['http://128.0.0.1', 'localhost:8000', [], false];
        yield 'an IPv6 origin other than ::1 for a loopback request' => ['http://[::2]:6274', 'localhost:8000', [], false];
        yield 'a loopback origin for an IPv6 loopback request' => ['http://localhost:6274', '[::1]:8000', [], true];
        yield 'a configured origin, case and trailing slash ignored' => ['https://Chat.Example/', 'shop.example', ['https://chat.example'], true];
        yield 'a configured origin on another port' => ['https://chat.example:8443', 'shop.example', ['https://chat.example'], false];
        yield 'a configured origin with any port' => ['http://localhost:6274', 'shop.example', ['http://localhost:*'], true];
        yield 'a configured origin with any port and another scheme' => ['https://localhost:6274', 'shop.example', ['http://localhost:*'], false];
        yield 'any origin' => ['https://attacker.example', 'shop.example', ['*'], true];
    }

    /**
     * @param list<string> $configuredOrigins
     */
    #[DataProvider('originProvider')]
    public function testIsAllowedOrigin(string $origin, string $requestHost, array $configuredOrigins, bool $expected): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['http://trunk.localhost:8088', 'https://de.shop.example/de']);

        $provider = new McpAllowedHostsProvider($connection, 'https://shop.example', $configuredOrigins);

        static::assertSame($expected, $provider->isAllowedOrigin($origin, $requestHost));
    }
}
