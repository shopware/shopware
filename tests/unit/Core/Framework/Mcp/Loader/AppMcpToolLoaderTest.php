<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Loader;

use Doctrine\DBAL\Exception as DBALException;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Tool;
use Mcp\Server\RequestContext;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureException;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\App\Mcp\Feature\McpToolConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Loader\AbstractAppMcpLoader;
use Shopware\Core\Framework\Mcp\Loader\AppMcpCapabilityExecutor;
use Shopware\Core\Framework\Mcp\Loader\AppMcpToolLoader;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;

/**
 * @internal
 */
#[CoversClass(AppMcpToolLoader::class)]
#[CoversClass(AbstractAppMcpLoader::class)]
#[Package('framework')]
class AppMcpToolLoaderTest extends TestCase
{
    private AppFeatureStorage&Stub $storage;

    private AppMcpCapabilityExecutor&Stub $executor;

    private LanguageLocaleCodeProvider&Stub $localeProvider;

    private AppMcpToolLoader $loader;

    protected function setUp(): void
    {
        $this->storage = static::createStub(AppFeatureStorage::class);
        $this->executor = static::createStub(AppMcpCapabilityExecutor::class);
        $this->localeProvider = static::createStub(LanguageLocaleCodeProvider::class);
        $this->localeProvider->method('getLocaleForLanguageId')->willReturn('en-GB');
        $this->loader = new AppMcpToolLoader($this->storage, $this->executor, $this->localeProvider, new NullLogger());
    }

    public function testLoadWithDBALExceptionRegistersNoTools(): void
    {
        $exception = new class('DB error') extends \Exception implements DBALException {};

        $this->storage->method('forActiveApps')->willThrowException($exception);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerTool');

        $this->loader->load($registry);
    }

    public function testLoadWithUnknownFeatureExceptionRegistersNoTools(): void
    {
        // the MCP feature type is only registered when MCP_SERVER is enabled
        $this->storage->method('forActiveApps')->willThrowException(AppFeatureException::unknownFeature(McpToolConfig::class));

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerTool');

        $this->loader->load($registry);
    }

    public function testLoadWithOneToolRegistersToolWithCorrectName(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->toolConfig())]);

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
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature($this->toolConfig(label: ['en-GB' => ''])),
        ]);

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
        $inputSchema = [
            'since' => [
                'type' => 'string',
                'description' => 'ISO date',
                'required' => true,
            ],
        ];

        $this->storage->method('forActiveApps')->willReturn([
            $this->feature($this->toolConfig(inputSchema: $inputSchema)),
        ]);

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

    public function testNullInputSchemaProducesEmptySchema(): void
    {
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature($this->toolConfig(inputSchema: null)),
        ]);

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

    public function testLoadWithEmptyAllowlistRegistersAllAppTools(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->toolConfig())]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())->method('registerTool');

        $loader = new AppMcpToolLoader($this->storage, $this->executor, $this->localeProvider, new NullLogger(), []);
        $loader->load($registry);
    }

    public function testLoadWithAllowlistRegistersOnlyAllowedAppTools(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->toolConfig())]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerTool')
            ->with(
                static::callback(fn (Tool $tool): bool => $tool->name === 'my-app-sync-orders'),
                static::isCallable(),
                true,
            );

        $loader = new AppMcpToolLoader($this->storage, $this->executor, $this->localeProvider, new NullLogger(), ['my-app-sync-orders']);
        $loader->load($registry);
    }

    public function testDescriptionFallsBackToToolNameWhenNoLabelOrDescription(): void
    {
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature($this->toolConfig(name: 'mystery-tool', label: [], description: [])),
        ]);

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
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature($this->toolConfig(), appVersion: '2.1.0'),
        ]);

        $executor = $this->createMock(AppMcpCapabilityExecutor::class);
        $executor->expects($this->once())
            ->method('execute')
            ->with('my-app-sync-orders', 'my-app', 'https://app.example.com/mcp/sync', ['since' => '2025-01-01'], '2.1.0')
            ->willReturn('{"success":true}');
        $loader = new AppMcpToolLoader($this->storage, $executor, $this->localeProvider, new NullLogger());

        $capturedCallback = null;
        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerTool')
            ->willReturnCallback(function (Tool $tool, callable $callback) use (&$capturedCallback): void {
                $capturedCallback = $callback;
            });

        $loader->load($registry);

        static::assertNotNull($capturedCallback);

        $request = new CallToolRequest('my-app-sync-orders', ['since' => '2025-01-01']);
        $context = new RequestContext(static::createStub(SessionInterface::class), $request);

        $result = ($capturedCallback)($context);
        static::assertSame('{"success":true}', $result);
    }

    public function testRegisteredCallbackWithNonCallToolRequestPassesEmptyArguments(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->toolConfig())]);

        $executor = $this->createMock(AppMcpCapabilityExecutor::class);
        $executor->expects($this->once())
            ->method('execute')
            ->with('my-app-sync-orders', 'my-app', 'https://app.example.com/mcp/sync', [], '0.0.0')
            ->willReturn('{"success":true}');
        $loader = new AppMcpToolLoader($this->storage, $executor, $this->localeProvider, new NullLogger());

        $capturedCallback = null;
        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerTool')
            ->willReturnCallback(function (Tool $tool, callable $callback) use (&$capturedCallback): void {
                $capturedCallback = $callback;
            });

        $loader->load($registry);

        static::assertNotNull($capturedCallback);

        $request = static::createStub(Request::class);
        $context = new RequestContext(static::createStub(SessionInterface::class), $request);

        $result = ($capturedCallback)($context);
        static::assertSame('{"success":true}', $result);
    }

    public function testLoadWithAllowlistSkipsAppToolNotInList(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->toolConfig())]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerTool');

        $loader = new AppMcpToolLoader($this->storage, $this->executor, $this->localeProvider, new NullLogger(), ['other-tool-only']);
        $loader->load($registry);
    }

    public function testEmptyStringDescriptionFallsBackToLabel(): void
    {
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature($this->toolConfig(description: ['en-GB' => ''])),
        ]);

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
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature($this->toolConfig(name: 'orders'), appName: 'shopware'),
        ]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerTool');

        $this->loader->load($registry);
    }

    public function testSkipsExternalUrlToolWhenAppHasNoSecret(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->toolConfig(), appHasSecret: false)]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerTool');

        $this->loader->load($registry);
    }

    public function testRegistersInternalUrlToolWhenAppHasNoSecret(): void
    {
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature($this->toolConfig(url: '/api/script/my-app-sync'), appHasSecret: false),
        ]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())->method('registerTool');

        $this->loader->load($registry);
    }

    /**
     * @param array<string, array{type: string, description?: string, required?: bool}>|null $inputSchema
     * @param array<string, string> $label
     * @param array<string, string> $description
     * @param list<string> $requiredPrivileges
     */
    private function toolConfig(
        string $name = 'sync-orders',
        string $url = 'https://app.example.com/mcp/sync',
        ?array $inputSchema = null,
        array $label = ['en-GB' => 'Sync Orders'],
        array $description = ['en-GB' => 'Syncs orders'],
        array $requiredPrivileges = [],
    ): McpToolConfig {
        return new McpToolConfig($name, $url, $requiredPrivileges, $inputSchema, new TranslatedString($label), new TranslatedString($description));
    }

    /**
     * @return AppFeature<McpToolConfig>
     */
    private function feature(McpToolConfig $config, string $appName = 'my-app', string $appVersion = '0.0.0', bool $appHasSecret = true): AppFeature
    {
        return new AppFeature('0189aaaabbbbcccc0000000000000001', $appName, true, $appVersion, $appHasSecret, new \DateTimeImmutable(), $config);
    }
}
