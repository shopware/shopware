<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Loader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Tool;
use Mcp\Server\RequestContext;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Loader\AbstractAppMcpLoader;
use Shopware\Core\Framework\Mcp\Loader\AppMcpCapabilityExecutor;
use Shopware\Core\Framework\Mcp\Loader\AppMcpToolLoader;

/**
 * @internal
 */
#[CoversClass(AppMcpToolLoader::class)]
#[CoversClass(AbstractAppMcpLoader::class)]
#[Package('framework')]
class AppMcpToolLoaderTest extends TestCase
{
    private Connection&MockObject $connection;

    private AppMcpCapabilityExecutor&MockObject $executor;

    private AppMcpToolLoader $loader;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->executor = $this->createMock(AppMcpCapabilityExecutor::class);
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
            'version' => '0.0.0',
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
                    static::assertSame('Sync Orders', $tool->title);
                    static::assertSame('Syncs orders', $tool->description);
                    static::assertSame(['type' => 'object', 'properties' => [], 'required' => []], $tool->inputSchema);

                    return true;
                }),
                static::isCallable(),
                true,
            );

        $this->loader->load($registry);
    }

    public function testTitleIsNullWhenLabelIsEmpty(): void
    {
        $toolRow = [
            'name' => 'sync-orders',
            'url' => 'https://app.example.com/mcp/sync',
            'input_schema' => null,
            'app_name' => 'my-app',
            'app_secret' => 'secret',
            'version' => '0.0.0',
            'label' => '',
            'description' => 'Syncs orders',
        ];

        $this->connection->method('fetchAllAssociative')->willReturn([$toolRow]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerTool')
            ->with(
                static::callback(function (Tool $tool): bool {
                    static::assertNull($tool->title);
                    static::assertSame('Syncs orders', $tool->description);

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
            'version' => '0.0.0',
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

    public function testLoadWithEmptyAllowlistRegistersAllAppTools(): void
    {
        $toolRow = [
            'name' => 'sync-orders',
            'url' => 'https://app.example.com/mcp/sync',
            'input_schema' => null,
            'app_name' => 'my-app',
            'app_secret' => 'secret',
            'version' => '0.0.0',
            'label' => 'Sync',
            'description' => 'Sync',
        ];

        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([$toolRow]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())->method('registerTool');

        $loader = new AppMcpToolLoader($this->connection, $this->executor, []);
        $loader->load($registry);
    }

    public function testLoadWithAllowlistRegistersOnlyAllowedAppTools(): void
    {
        $toolRow = [
            'name' => 'sync-orders',
            'url' => 'https://app.example.com/mcp/sync',
            'input_schema' => null,
            'app_name' => 'my-app',
            'app_secret' => 'secret',
            'version' => '0.0.0',
            'label' => 'Sync',
            'description' => 'Sync',
        ];

        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([$toolRow]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerTool')
            ->with(
                static::callback(fn (Tool $tool): bool => $tool->name === 'my-app-sync-orders'),
                static::isCallable(),
                true,
            );

        $loader = new AppMcpToolLoader($this->connection, $this->executor, ['my-app-sync-orders']);
        $loader->load($registry);
    }

    public function testInvalidInputSchemaJsonFallsBackToEmptySchema(): void
    {
        $toolRow = [
            'name' => 'broken-tool',
            'url' => 'https://app.example.com/mcp/broken',
            'input_schema' => 'not-valid-json',
            'app_name' => 'my-app',
            'app_secret' => 'secret',
            'version' => '0.0.0',
            'label' => 'Broken',
            'description' => 'Broken tool',
        ];

        $this->connection->method('fetchAllAssociative')->willReturn([$toolRow]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerTool')
            ->with(
                static::callback(function (Tool $tool): bool {
                    static::assertSame(['type' => 'object', 'properties' => [], 'required' => []], $tool->inputSchema);

                    return true;
                }),
                static::isCallable(),
                true,
            );

        $this->loader->load($registry);
    }

    public function testDescriptionFallsBackToToolNameWhenNoLabelOrDescription(): void
    {
        $toolRow = [
            'name' => 'mystery-tool',
            'url' => 'https://app.example.com/mcp/mystery',
            'input_schema' => null,
            'app_name' => 'my-app',
            'app_secret' => 'secret',
            'version' => '0.0.0',
            'label' => null,
            'description' => null,
        ];

        $this->connection->method('fetchAllAssociative')->willReturn([$toolRow]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerTool')
            ->with(
                static::callback(function (Tool $tool): bool {
                    static::assertNull($tool->title);
                    static::assertSame('my-app-mystery-tool', $tool->description);

                    return true;
                }),
                static::isCallable(),
                true,
            );

        $this->loader->load($registry);
    }

    public function testRegisteredCallbackInvokesExecutorWithArguments(): void
    {
        $toolRow = [
            'name' => 'sync-orders',
            'url' => 'https://app.example.com/mcp/sync',
            'input_schema' => null,
            'app_name' => 'my-app',
            'app_secret' => 'test-secret',
            'version' => '2.1.0',
            'label' => 'Sync Orders',
            'description' => 'Syncs orders',
        ];

        $this->connection->method('fetchAllAssociative')->willReturn([$toolRow]);

        $this->executor->expects($this->once())
            ->method('execute')
            ->with('my-app-sync-orders', 'test-secret', 'https://app.example.com/mcp/sync', ['since' => '2025-01-01'], '2.1.0')
            ->willReturn('{"success":true}');

        $capturedCallback = null;
        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerTool')
            ->willReturnCallback(function (Tool $tool, callable $callback) use (&$capturedCallback): void {
                $capturedCallback = $callback;
            });

        $this->loader->load($registry);

        static::assertNotNull($capturedCallback);

        $request = new CallToolRequest('my-app-sync-orders', ['since' => '2025-01-01']);
        $context = new RequestContext(static::createStub(SessionInterface::class), $request);

        $result = ($capturedCallback)($context);
        static::assertSame('{"success":true}', $result);
    }

    public function testRegisteredCallbackWithNonCallToolRequestPassesEmptyArguments(): void
    {
        $toolRow = [
            'name' => 'sync-orders',
            'url' => 'https://app.example.com/mcp/sync',
            'input_schema' => null,
            'app_name' => 'my-app',
            'app_secret' => 'test-secret',
            'version' => '0.0.0',
            'label' => 'Sync Orders',
            'description' => 'Syncs orders',
        ];

        $this->connection->method('fetchAllAssociative')->willReturn([$toolRow]);

        $this->executor->expects($this->once())
            ->method('execute')
            ->with('my-app-sync-orders', 'test-secret', 'https://app.example.com/mcp/sync', [], '0.0.0')
            ->willReturn('{"success":true}');

        $capturedCallback = null;
        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerTool')
            ->willReturnCallback(function (Tool $tool, callable $callback) use (&$capturedCallback): void {
                $capturedCallback = $callback;
            });

        $this->loader->load($registry);

        static::assertNotNull($capturedCallback);

        $request = static::createStub(Request::class);
        $context = new RequestContext(static::createStub(SessionInterface::class), $request);

        $result = ($capturedCallback)($context);
        static::assertSame('{"success":true}', $result);
    }

    public function testLoadWithAllowlistSkipsAppToolNotInList(): void
    {
        $toolRow = [
            'name' => 'sync-orders',
            'url' => 'https://app.example.com/mcp/sync',
            'input_schema' => null,
            'app_name' => 'my-app',
            'app_secret' => 'secret',
            'version' => '0.0.0',
            'label' => 'Sync',
            'description' => 'Sync',
        ];

        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([$toolRow]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerTool');

        $loader = new AppMcpToolLoader($this->connection, $this->executor, ['other-tool-only']);
        $loader->load($registry);
    }

    public function testEmptyStringDescriptionFallsBackToLabel(): void
    {
        $toolRow = [
            'name' => 'sync-orders',
            'url' => 'https://app.example.com/mcp/sync',
            'input_schema' => null,
            'app_name' => 'my-app',
            'app_secret' => 'secret',
            'version' => '0.0.0',
            'label' => 'Sync Orders',
            'description' => '',
        ];

        $this->connection->method('fetchAllAssociative')->willReturn([$toolRow]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerTool')
            ->with(
                static::callback(function (Tool $tool): bool {
                    static::assertSame('Sync Orders', $tool->description);

                    return true;
                }),
                static::isCallable(),
                true,
            );

        $this->loader->load($registry);
    }

    public function testLoadSkipsReservedShopwarePrefixedToolName(): void
    {
        $toolRow = [
            'name' => 'orders',
            'url' => 'https://app.example.com/mcp/sync',
            'input_schema' => null,
            'app_name' => 'shopware',
            'app_secret' => 'secret',
            'version' => '0.0.0',
            'label' => 'Sync',
            'description' => 'Sync',
        ];

        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([$toolRow]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerTool');

        $this->loader->load($registry);
    }
}
