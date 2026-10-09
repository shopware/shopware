<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Loader;

use Mcp\Capability\Registry\ResourceReference;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\ResourceDefinition;
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
#[Package('framework')]
#[CoversClass(AppMcpResourceLoader::class)]
#[CoversClass(AbstractAppMcpLoader::class)]
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

    public function testAResourceIsRegisteredUnderItsAppName(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->resourceConfig())]);

        static::assertEquals([
            new ResourceDefinition(
                uri: 'app-example://order-stats',
                name: 'my-app-order-stats',
                description: 'Live order statistics',
                mimeType: 'application/json',
            ),
        ], $this->registeredResources($this->loader));
    }

    public function testAResourceWithoutMimeTypeIsRegisteredWithoutOne(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->resourceConfig(mimeType: null))]);

        static::assertNull($this->registeredResources($this->loader)[0]->mimeType);
    }

    public function testAResourceWithAnEmptyLabelAndDescriptionIsDescribedByItsName(): void
    {
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature($this->resourceConfig(label: ['en-GB' => ''], description: [])),
        ]);

        static::assertSame('my-app-order-stats', $this->registeredResources($this->loader)[0]->description);
    }

    public function testAResourceNamedWithTheShopwarePrefixIsSkipped(): void
    {
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature($this->resourceConfig(name: 'entities'), appName: 'shopware'),
        ]);

        static::assertSame([], $this->registeredResources($this->loader));
    }

    public function testAResourceOfAnAppWithoutSecretIsSkipped(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->resourceConfig(), appHasSecret: false)]);

        static::assertSame([], $this->registeredResources($this->loader));
    }

    public function testReadingAResourceSendsItsUriToTheApp(): void
    {
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

        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->resourceConfig())]);
        $loader = new AppMcpResourceLoader($this->storage, $executor, $this->localeProvider, new NullLogger());
        $request = static::createStub(Request::class);

        $result = $this->registeredHandler($loader)(new RequestContext(static::createStub(SessionInterface::class), $request));

        static::assertSame('{"contents":[]}', $result);
    }

    /**
     * @return list<ResourceDefinition>
     */
    private function registeredResources(AppMcpResourceLoader $loader): array
    {
        $resources = [];

        $registry = static::createStub(RegistryInterface::class);
        $registry->method('registerResource')->willReturnCallback(function (ResourceDefinition $resource) use (&$resources): ResourceReference {
            $resources[] = $resource;

            return static::createStub(ResourceReference::class);
        });

        $loader->load($registry);

        return $resources;
    }

    private function registeredHandler(AppMcpResourceLoader $loader): callable
    {
        $handler = null;

        $registry = static::createStub(RegistryInterface::class);
        $registry->method('registerResource')->willReturnCallback(function (ResourceDefinition $resource, callable $registered) use (&$handler): ResourceReference {
            $handler = $registered;

            return static::createStub(ResourceReference::class);
        });

        $loader->load($registry);

        static::assertNotNull($handler);

        return $handler;
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
