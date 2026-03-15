<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\InstanceInfoTool;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InstanceInfoTool::class)]
class InstanceInfoToolTest extends TestCase
{
    private KernelInterface&MockObject $kernel;

    private ContainerInterface&MockObject $container;

    private Connection&MockObject $connection;

    private InstanceInfoTool $tool;

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(KernelInterface::class);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->connection = $this->createMock(Connection::class);

        $this->kernel->method('getContainer')->willReturn($this->container);
        $this->kernel->method('getEnvironment')->willReturn('test');
        $this->kernel->method('getProjectDir')->willReturn('/var/www/html');

        $this->tool = new InstanceInfoTool($this->kernel, $this->connection);
    }

    public function testReturnTypeIsAlwaysString(): void
    {
        $this->container->method('hasParameter')->willReturn(false);
        $this->connection->method('fetchAllAssociative')->willReturn([]);
        $this->connection->method('fetchOne')->willReturn('0');

        $result = ($this->tool)();
        static::assertIsString($result);

        $decoded = json_decode($result, true);
        static::assertIsArray($decoded);
        static::assertArrayHasKey('success', $decoded);
    }

    public function testVersionResolvesFromContainerParameter(): void
    {
        $this->container->method('hasParameter')
            ->with('shopware.version')
            ->willReturn(true);
        $this->container->method('getParameter')
            ->with('shopware.version')
            ->willReturn('6.7.7.0');

        $this->connection->method('fetchAllAssociative')->willReturn([]);
        $this->connection->method('fetchOne')->willReturn('0');

        $result = json_decode(($this->tool)(), true);

        static::assertTrue($result['success']);
        static::assertEquals('6.7.7.0', $result['data']['shopware_version']);
    }

    public function testEnvironmentIsIncluded(): void
    {
        $this->container->method('hasParameter')->willReturn(false);
        $this->connection->method('fetchAllAssociative')->willReturn([]);
        $this->connection->method('fetchOne')->willReturn('0');

        $result = json_decode(($this->tool)(), true);

        static::assertEquals('test', $result['data']['environment']);
    }

    public function testPhpVersionIsIncluded(): void
    {
        $this->container->method('hasParameter')->willReturn(false);
        $this->connection->method('fetchAllAssociative')->willReturn([]);
        $this->connection->method('fetchOne')->willReturn('0');

        $result = json_decode(($this->tool)(), true);

        static::assertEquals(\PHP_VERSION, $result['data']['php_version']);
    }

    public function testPluginsAreReturnedStructured(): void
    {
        $this->container->method('hasParameter')->willReturn(false);
        $this->connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'AcmePlugin', 'version' => '1.0.0', 'active' => '1', 'installed_at' => '2026-03-15 10:00:00'],
            ['name' => 'SwagPayPal', 'version' => '9.0.0', 'active' => '0', 'installed_at' => null],
        ]);
        $this->connection->method('fetchOne')->willReturn('0');

        $result = json_decode(($this->tool)(), true);
        $plugins = $result['data']['plugins'];

        static::assertCount(2, $plugins);
        static::assertEquals('AcmePlugin', $plugins[0]['name']);
        static::assertTrue($plugins[0]['active']);
        static::assertEquals('SwagPayPal', $plugins[1]['name']);
        static::assertFalse($plugins[1]['active']);
        static::assertNull($plugins[1]['installed_at']);
    }

    public function testMigrationsAreReturnedStructured(): void
    {
        $this->container->method('hasParameter')->willReturn(false);
        $this->connection->method('fetchAllAssociative')->willReturn([]);
        $this->connection->method('fetchOne')->willReturnOnConsecutiveCalls('150', '152');

        $result = json_decode(($this->tool)(), true);
        $migrations = $result['data']['migrations'];

        static::assertEquals(150, $migrations['executed']);
        static::assertEquals(152, $migrations['total']);
        static::assertEquals(2, $migrations['pending']);
    }

    public function testUninitializedInstanceGracefullyReturnsEmptyPlugins(): void
    {
        $this->container->method('hasParameter')->willReturn(false);
        $this->connection->method('fetchAllAssociative')
            ->willThrowException(new \RuntimeException('Table plugin does not exist'));
        $this->connection->method('fetchOne')->willReturn('0');

        $result = json_decode(($this->tool)(), true);

        static::assertTrue($result['success']);
        static::assertIsArray($result['data']['plugins']);
        static::assertEmpty($result['data']['plugins']);
    }

    public function testMigrationTableMissingGracefullyReturnsNote(): void
    {
        $this->container->method('hasParameter')->willReturn(false);
        $this->connection->method('fetchAllAssociative')->willReturn([]);
        $this->connection->method('fetchOne')
            ->willThrowException(new \RuntimeException('Table migration does not exist'));

        $result = json_decode(($this->tool)(), true);
        $migrations = $result['data']['migrations'];

        static::assertTrue($result['success']);
        static::assertEquals(0, $migrations['executed']);
        static::assertArrayHasKey('note', $migrations);
    }

    public function testTipIsAlwaysPresent(): void
    {
        $this->container->method('hasParameter')->willReturn(false);
        $this->connection->method('fetchAllAssociative')->willReturn([]);
        $this->connection->method('fetchOne')->willReturn('0');

        $result = json_decode(($this->tool)(), true);

        static::assertArrayHasKey('tip', $result['data']);
        static::assertStringContainsString('debug:mcp', $result['data']['tip']);
    }
}
