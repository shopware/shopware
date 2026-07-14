<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Loader;

use Doctrine\DBAL\Exception as DBALException;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\Resource;
use Mcp\Server\RequestContext;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\App\Mcp\Feature\McpResourceConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Loader\AbstractAppMcpLoader;
use Shopware\Core\Framework\Mcp\Loader\AppMcpCapabilityExecutor;
use Shopware\Core\Framework\Mcp\Loader\AppMcpResourceLoader;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;

/**
 * @internal
 */
#[CoversClass(AppMcpResourceLoader::class)]
#[CoversClass(AbstractAppMcpLoader::class)]
#[Package('framework')]
class AppMcpResourceLoaderTest extends TestCase
{
    private AppFeatureStorage&Stub $storage;

    private AppMcpCapabilityExecutor&Stub $executor;

    private LanguageLocaleCodeProvider&Stub $localeProvider;

    private AppMcpResourceLoader $loader;

    protected function setUp(): void
    {
        $this->storage = static::createStub(AppFeatureStorage::class);
        $this->executor = static::createStub(AppMcpCapabilityExecutor::class);
        $this->localeProvider = static::createStub(LanguageLocaleCodeProvider::class);
        $this->localeProvider->method('getLocaleForLanguageId')->willReturn('en-GB');
        $this->loader = new AppMcpResourceLoader($this->storage, $this->executor, $this->localeProvider, new NullLogger());
    }

    public function testLoadWithDBALExceptionRegistersNoResources(): void
    {
        $exception = new class('DB error') extends \Exception implements DBALException {};

        $this->storage->method('forActiveApps')->willThrowException($exception);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerResource');

        $this->loader->load($registry);
    }

    public function testLoadWithOneResourceRegistersResourceWithCorrectProperties(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->resourceConfig())]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerResource')
            ->with(
                static::callback(function (Resource $resource): bool {
                    static::assertSame('my-app-order-stats', $resource->name);
                    static::assertSame('app-example://order-stats', $resource->uri);
                    static::assertSame('Live order statistics', $resource->description);
                    static::assertSame('application/json', $resource->mimeType);

                    return true;
                }),
                static::isCallable(),
                true,
            );

        $this->loader->load($registry);
    }

    public function testLoadWithNullMimeTypeRegistersResourceWithoutMimeType(): void
    {
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature($this->resourceConfig(mimeType: null, description: [])),
        ]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerResource')
            ->with(
                static::callback(function (Resource $resource): bool {
                    static::assertNull($resource->mimeType);

                    return true;
                }),
                static::isCallable(),
                true,
            );

        $this->loader->load($registry);
    }

    public function testDescriptionFallsBackToResourceNameWhenNoLabelOrDescription(): void
    {
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature($this->resourceConfig(name: 'mystery-resource', label: [], description: [], mimeType: null)),
        ]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerResource')
            ->with(
                static::callback(function (Resource $resource): bool {
                    static::assertSame('my-app-mystery-resource', $resource->description);

                    return true;
                }),
                static::isCallable(),
                true,
            );

        $this->loader->load($registry);
    }

    public function testRegisteredCallbackInvokesExecutorWithUri(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->resourceConfig())]);

        $executor = $this->createMock(AppMcpCapabilityExecutor::class);
        $executor->expects($this->once())
            ->method('execute')
            ->with(
                'my-app-order-stats',
                'my-app',
                'https://app.example.com/mcp/resource/order-stats',
                ['uri' => 'app-example://order-stats'],
            )
            ->willReturn('{"contents":[]}');
        $loader = new AppMcpResourceLoader($this->storage, $executor, $this->localeProvider, new NullLogger());

        $capturedCallback = null;
        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerResource')
            ->willReturnCallback(function (Resource $resource, callable $callback) use (&$capturedCallback): void {
                $capturedCallback = $callback;
            });

        $loader->load($registry);

        static::assertNotNull($capturedCallback);

        $context = new RequestContext(
            static::createStub(SessionInterface::class),
            static::createStub(Request::class),
        );

        $result = ($capturedCallback)($context);
        static::assertSame('{"contents":[]}', $result);
    }

    public function testLoadWithEmptyResultRegistersNoResources(): void
    {
        $this->storage->method('forActiveApps')->willReturn([]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerResource');

        $this->loader->load($registry);
    }

    public function testResourceWithReservedShopwarePrefixIsSkipped(): void
    {
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature($this->resourceConfig(name: 'entities', uri: 'shopware://entities', mimeType: null), appName: 'shopware'),
        ]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerResource');

        $this->loader->load($registry);
    }

    public function testSkipsResourceWhenAppHasNoSecret(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->resourceConfig(), appHasSecret: false)]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerResource');

        $this->loader->load($registry);
    }

    /**
     * @param array<string, string> $label
     * @param array<string, string> $description
     */
    private function resourceConfig(
        string $name = 'order-stats',
        string $uri = 'app-example://order-stats',
        string $url = 'https://app.example.com/mcp/resource/order-stats',
        ?string $mimeType = 'application/json',
        array $label = ['en-GB' => 'Order Stats'],
        array $description = ['en-GB' => 'Live order statistics'],
    ): McpResourceConfig {
        return new McpResourceConfig($name, $uri, $url, $mimeType, new TranslatedString($label), new TranslatedString($description));
    }

    /**
     * @return AppFeature<McpResourceConfig>
     */
    private function feature(McpResourceConfig $config, string $appName = 'my-app', bool $appHasSecret = true): AppFeature
    {
        return new AppFeature('0189aaaabbbbcccc0000000000000001', $appName, true, '0.0.0', $appHasSecret, new \DateTimeImmutable(), $config);
    }
}
