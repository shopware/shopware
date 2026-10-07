<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Loader;

use Mcp\Capability\Registry\ToolReference;
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
#[Package('framework')]
#[CoversClass(AppMcpToolLoader::class)]
#[CoversClass(AbstractAppMcpLoader::class)]
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

    public function testAToolIsRegisteredUnderItsAppName(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->toolConfig())]);

        static::assertEquals([
            new Tool(
                name: 'my-app-sync-orders',
                title: 'Sync Orders',
                inputSchema: ['type' => 'object', 'properties' => [], 'required' => []],
                description: 'Imports new orders from the ERP',
                annotations: null,
            ),
        ], $this->registeredTools($this->loader));
    }

    public function testTheInputSchemaBecomesAJsonSchema(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->toolConfig(inputSchema: [
            'since' => ['type' => 'string', 'description' => 'ISO date', 'required' => true],
            'limit' => ['type' => 'integer'],
        ]))]);

        static::assertEquals([
            new Tool(
                name: 'my-app-sync-orders',
                title: 'Sync Orders',
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'since' => ['type' => 'string', 'description' => 'ISO date'],
                        'limit' => ['type' => 'integer'],
                    ],
                    'required' => ['since'],
                ],
                description: 'Imports new orders from the ERP',
                annotations: null,
            ),
        ], $this->registeredTools($this->loader));
    }

    public function testAnEmptyDescriptionFallsBackToTheLabel(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->toolConfig(description: ['en-GB' => '']))]);

        static::assertSame('Sync Orders', $this->registeredTools($this->loader)[0]->description);
    }

    public function testAToolWithAnEmptyLabelAndDescriptionIsDescribedByItsName(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->toolConfig(label: ['en-GB' => ''], description: []))]);

        $tool = $this->registeredTools($this->loader)[0];

        static::assertNull($tool->title);
        static::assertSame('my-app-sync-orders', $tool->description);
    }

    public function testOnlyAllowedToolsAreRegistered(): void
    {
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature($this->toolConfig(name: 'sync-orders')),
            $this->feature($this->toolConfig(name: 'stock-check')),
        ]);
        $loader = new AppMcpToolLoader($this->storage, $this->executor, $this->localeProvider, new NullLogger(), ['my-app-sync-orders']);

        $tools = $this->registeredTools($loader);

        static::assertCount(1, $tools);
        static::assertSame('my-app-sync-orders', $tools[0]->name);
    }

    public function testAToolNamedWithTheShopwarePrefixIsSkipped(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->toolConfig(name: 'orders'), appName: 'shopware')]);

        static::assertSame([], $this->registeredTools($this->loader));
    }

    public function testAnExternalToolOfAnAppWithoutSecretIsSkipped(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->toolConfig(), appHasSecret: false)]);

        static::assertSame([], $this->registeredTools($this->loader));
    }

    public function testAnInternalToolOfAnAppWithoutSecretIsRegistered(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->toolConfig(url: '/api/script/my-app-sync'), appHasSecret: false)]);

        static::assertCount(1, $this->registeredTools($this->loader));
    }

    public function testCallingAToolSendsItsArgumentsToTheApp(): void
    {
        $executor = $this->createMock(AppMcpCapabilityExecutor::class);
        $executor->expects($this->once())
            ->method('execute')
            ->with('my-app-sync-orders', 'my-app', 'https://app.example.com/mcp/sync', ['since' => '2025-01-01'], '2.1.0')
            ->willReturn('{"success":true}');

        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->toolConfig(), appVersion: '2.1.0')]);
        $loader = new AppMcpToolLoader($this->storage, $executor, $this->localeProvider, new NullLogger());
        $request = new CallToolRequest('my-app-sync-orders', ['since' => '2025-01-01']);

        $result = $this->registeredHandler($loader)(new RequestContext(static::createStub(SessionInterface::class), $request));

        static::assertSame('{"success":true}', $result);
    }

    public function testARequestThatIsNoToolCallSendsNoArguments(): void
    {
        $executor = $this->createMock(AppMcpCapabilityExecutor::class);
        $executor->expects($this->once())
            ->method('execute')
            ->with('my-app-sync-orders', 'my-app', 'https://app.example.com/mcp/sync', [], '0.0.0')
            ->willReturn('{"success":true}');

        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->toolConfig())]);
        $loader = new AppMcpToolLoader($this->storage, $executor, $this->localeProvider, new NullLogger());
        $request = static::createStub(Request::class);

        $result = $this->registeredHandler($loader)(new RequestContext(static::createStub(SessionInterface::class), $request));

        static::assertSame('{"success":true}', $result);
    }

    /**
     * @return list<Tool>
     */
    private function registeredTools(AppMcpToolLoader $loader): array
    {
        $tools = [];

        $registry = static::createStub(RegistryInterface::class);
        $registry->method('registerTool')->willReturnCallback(function (Tool $tool) use (&$tools): ToolReference {
            $tools[] = $tool;

            return static::createStub(ToolReference::class);
        });

        $loader->load($registry);

        return $tools;
    }

    private function registeredHandler(AppMcpToolLoader $loader): callable
    {
        $handler = null;

        $registry = static::createStub(RegistryInterface::class);
        $registry->method('registerTool')->willReturnCallback(function (Tool $tool, callable $registered) use (&$handler): ToolReference {
            $handler = $registered;

            return static::createStub(ToolReference::class);
        });

        $loader->load($registry);

        static::assertNotNull($handler);

        return $handler;
    }

    /**
     * @param array<string, array{type: string, description?: string, required?: bool}>|null $inputSchema
     * @param array<string, string> $label
     * @param array<string, string> $description
     */
    private function toolConfig(
        string $name = 'sync-orders',
        string $url = 'https://app.example.com/mcp/sync',
        ?array $inputSchema = null,
        array $label = ['en-GB' => 'Sync Orders'],
        array $description = ['en-GB' => 'Imports new orders from the ERP'],
    ): McpToolConfig {
        return new McpToolConfig($name, $url, [], $inputSchema, new TranslatedString($label), new TranslatedString($description));
    }

    /**
     * @return AppFeature<McpToolConfig>
     */
    private function feature(McpToolConfig $config, string $appName = 'my-app', string $appVersion = '0.0.0', bool $appHasSecret = true): AppFeature
    {
        return new AppFeature('0189aaaabbbbcccc0000000000000001', $appName, true, $appVersion, $appHasSecret, new \DateTimeImmutable(), $config);
    }
}
