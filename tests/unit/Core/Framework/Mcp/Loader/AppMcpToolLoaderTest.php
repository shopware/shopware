<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Loader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Loader\AppMcpToolExecutor;
use Shopware\Core\Framework\Mcp\Loader\AppMcpToolLoader;

/**
 * @internal
 */
#[CoversClass(AppMcpToolLoader::class)]
#[Package('framework')]
class AppMcpToolLoaderTest extends TestCase
{
    private Connection&MockObject $connection;

    private AppMcpToolExecutor&MockObject $executor;

    private AppMcpToolLoader $loader;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->executor = $this->createMock(AppMcpToolExecutor::class);
        $this->loader = new AppMcpToolLoader($this->connection, $this->executor);
    }

    public function testLoadWithDBALExceptionRegistersNoTools(): void
    {
        $exception = new class('DB error') extends \Exception implements DBALException {};

        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willThrowException($exception);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerTool');

        $this->loader->load($registry);
    }

    public function testLoadWithOneToolRegistersToolWithCorrectName(): void
    {
        $toolRow = [
            'name' => 'sync-orders',
            'url' => 'https://app.example.com/mcp/sync',
            'input_schema' => null,
            'app_name' => 'my-app',
            'app_secret' => 'test-secret',
            'label' => 'Sync Orders',
            'description' => 'Syncs orders',
        ];

        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([$toolRow]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerTool')
            ->with(
                static::callback(function (Tool $tool): bool {
                    static::assertSame('my-app-sync-orders', $tool->name);
                    static::assertSame('Sync Orders', $tool->description);
                    static::assertSame(['type' => 'object', 'properties' => [], 'required' => []], $tool->inputSchema);

                    return true;
                }),
                static::isCallable(),
                true,
            );

        $this->loader->load($registry);
    }

    public function testLoadWithInputSchemaRegistersToolWithCorrectInputSchema(): void
    {
        $inputSchemaJson = json_encode([
            'since' => [
                'type' => 'string',
                'description' => 'ISO date',
                'required' => true,
            ],
        ]);

        $toolRow = [
            'name' => 'sync-orders',
            'url' => 'https://app.example.com/mcp/sync',
            'input_schema' => $inputSchemaJson,
            'app_name' => 'my-app',
            'app_secret' => 'test-secret',
            'label' => 'Sync Orders',
            'description' => 'Syncs orders',
        ];

        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([$toolRow]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerTool')
            ->with(
                static::callback(function (Tool $tool): bool {
                    static::assertSame('my-app-sync-orders', $tool->name);
                    static::assertArrayHasKey('since', $tool->inputSchema['properties']);
                    static::assertSame('string', $tool->inputSchema['properties']['since']['type']);
                    static::assertSame('ISO date', $tool->inputSchema['properties']['since']['description']);
                    static::assertIsArray($tool->inputSchema['required']);
                    static::assertContains('since', $tool->inputSchema['required']);

                    return true;
                }),
                static::isCallable(),
                true,
            );

        $this->loader->load($registry);
    }
}
