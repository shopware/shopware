<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Loader;

use Mcp\Capability\Registry\PromptReference;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\Prompt;
use Mcp\Server\RequestContext;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\App\Mcp\Feature\McpPromptConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Loader\AbstractAppMcpLoader;
use Shopware\Core\Framework\Mcp\Loader\AppMcpCapabilityExecutor;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPromptLoader;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppMcpPromptLoader::class)]
#[CoversClass(AbstractAppMcpLoader::class)]
class AppMcpPromptLoaderTest extends TestCase
{
    private AppFeatureStorage&Stub $storage;

    private AppMcpCapabilityExecutor&Stub $executor;

    private LanguageLocaleCodeProvider&Stub $localeProvider;

    private AppMcpPromptLoader $loader;

    protected function setUp(): void
    {
        $this->storage = static::createStub(AppFeatureStorage::class);
        $this->executor = static::createStub(AppMcpCapabilityExecutor::class);
        $this->localeProvider = static::createStub(LanguageLocaleCodeProvider::class);
        $this->localeProvider->method('getLocaleForLanguageId')->willReturn('en-GB');
        $this->loader = new AppMcpPromptLoader($this->storage, $this->executor, $this->localeProvider, new NullLogger());
    }

    public function testAPromptIsRegisteredUnderItsAppName(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->promptConfig())]);

        static::assertEquals([
            new Prompt(
                name: 'my-app-order-context',
                title: 'Order Context',
                description: 'Context for order management',
            ),
        ], $this->registeredPrompts($this->loader));
    }

    public function testAPromptWithAnEmptyLabelAndDescriptionIsDescribedByItsName(): void
    {
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature($this->promptConfig(label: ['en-GB' => ''], description: [])),
        ]);

        $prompt = $this->registeredPrompts($this->loader)[0];

        static::assertNull($prompt->title);
        static::assertSame('my-app-order-context', $prompt->description);
    }

    public function testAPromptNamedWithTheShopwarePrefixIsSkipped(): void
    {
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature($this->promptConfig(name: 'context'), appName: 'shopware'),
        ]);

        static::assertSame([], $this->registeredPrompts($this->loader));
    }

    public function testAPromptOfAnAppWithoutSecretIsSkipped(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->promptConfig(), appHasSecret: false)]);

        static::assertSame([], $this->registeredPrompts($this->loader));
    }

    public function testGettingAPromptCallsTheAppWithoutArguments(): void
    {
        $executor = $this->createMock(AppMcpCapabilityExecutor::class);
        $executor->expects($this->once())
            ->method('execute')
            ->with('my-app-order-context', 'my-app', 'https://app.example.com/mcp/prompt/order-context', [])
            ->willReturn('{"messages":[]}');

        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->promptConfig())]);
        $loader = new AppMcpPromptLoader($this->storage, $executor, $this->localeProvider, new NullLogger());
        $request = static::createStub(Request::class);

        $result = $this->registeredHandler($loader)(new RequestContext(static::createStub(SessionInterface::class), $request));

        static::assertSame('{"messages":[]}', $result);
    }

    /**
     * @return list<Prompt>
     */
    private function registeredPrompts(AppMcpPromptLoader $loader): array
    {
        $prompts = [];

        $registry = static::createStub(RegistryInterface::class);
        $registry->method('registerPrompt')->willReturnCallback(function (Prompt $prompt) use (&$prompts): PromptReference {
            $prompts[] = $prompt;

            return static::createStub(PromptReference::class);
        });

        $loader->load($registry);

        return $prompts;
    }

    private function registeredHandler(AppMcpPromptLoader $loader): callable
    {
        $handler = null;

        $registry = static::createStub(RegistryInterface::class);
        $registry->method('registerPrompt')->willReturnCallback(function (Prompt $prompt, callable $registered) use (&$handler): PromptReference {
            $handler = $registered;

            return static::createStub(PromptReference::class);
        });

        $loader->load($registry);

        static::assertNotNull($handler);

        return $handler;
    }

    /**
     * @param array<string, string> $label
     * @param array<string, string> $description
     */
    private function promptConfig(
        string $name = 'order-context',
        string $url = 'https://app.example.com/mcp/prompt/order-context',
        array $label = ['en-GB' => 'Order Context'],
        array $description = ['en-GB' => 'Context for order management'],
    ): McpPromptConfig {
        return new McpPromptConfig($name, $url, new TranslatedString($label), new TranslatedString($description));
    }

    /**
     * @return AppFeature<McpPromptConfig>
     */
    private function feature(McpPromptConfig $config, string $appName = 'my-app', bool $appHasSecret = true): AppFeature
    {
        return new AppFeature('0189aaaabbbbcccc0000000000000001', $appName, true, '0.0.0', $appHasSecret, new \DateTimeImmutable(), $config);
    }
}
