<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Loader;

use Doctrine\DBAL\Exception as DBALException;
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
#[CoversClass(AppMcpPromptLoader::class)]
#[CoversClass(AbstractAppMcpLoader::class)]
#[Package('framework')]
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

    public function testLoadWithDBALExceptionRegistersNoPrompts(): void
    {
        $exception = new class('DB error') extends \Exception implements DBALException {};

        $this->storage->method('forActiveApps')->willThrowException($exception);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerPrompt');

        $this->loader->load($registry);
    }

    public function testLoadWithOnePromptRegistersPromptWithCorrectName(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->promptConfig())]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerPrompt')
            ->with(
                static::callback(function (Prompt $prompt): bool {
                    static::assertSame('my-app-order-context', $prompt->name);
                    static::assertSame('Order Context', $prompt->title);
                    static::assertSame('Context for order management', $prompt->description);

                    return true;
                }),
                static::isCallable(),
                [],
                true,
            );

        $this->loader->load($registry);
    }

    public function testTitleIsNullWhenLabelIsEmpty(): void
    {
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature($this->promptConfig(label: ['en-GB' => ''])),
        ]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerPrompt')
            ->with(
                static::callback(function (Prompt $prompt): bool {
                    static::assertNull($prompt->title);
                    static::assertSame('Context for order management', $prompt->description);

                    return true;
                }),
                static::isCallable(),
                [],
                true,
            );

        $this->loader->load($registry);
    }

    public function testDescriptionFallsBackToPromptNameWhenNoLabelOrDescription(): void
    {
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature($this->promptConfig(name: 'mystery-prompt', label: [], description: [])),
        ]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerPrompt')
            ->with(
                static::callback(function (Prompt $prompt): bool {
                    static::assertNull($prompt->title);
                    static::assertSame('my-app-mystery-prompt', $prompt->description);

                    return true;
                }),
                static::isCallable(),
                [],
                true,
            );

        $this->loader->load($registry);
    }

    public function testRegisteredCallbackInvokesExecutorWithEmptyArguments(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->promptConfig())]);

        $executor = $this->createMock(AppMcpCapabilityExecutor::class);
        $executor->expects($this->once())
            ->method('execute')
            ->with('my-app-order-context', 'my-app', 'https://app.example.com/mcp/prompt/order-context', [])
            ->willReturn('{"messages":[]}');
        $loader = new AppMcpPromptLoader($this->storage, $executor, $this->localeProvider, new NullLogger());

        $capturedCallback = null;
        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerPrompt')
            ->willReturnCallback(function (Prompt $prompt, callable $callback) use (&$capturedCallback): void {
                $capturedCallback = $callback;
            });

        $loader->load($registry);

        static::assertNotNull($capturedCallback);

        $context = new RequestContext(
            static::createStub(SessionInterface::class),
            static::createStub(Request::class),
        );

        $result = ($capturedCallback)($context);
        static::assertSame('{"messages":[]}', $result);
    }

    public function testLoadWithEmptyResultRegistersNoPrompts(): void
    {
        $this->storage->method('forActiveApps')->willReturn([]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerPrompt');

        $this->loader->load($registry);
    }

    public function testPromptWithReservedShopwarePrefixIsSkipped(): void
    {
        $this->storage->method('forActiveApps')->willReturn([
            $this->feature($this->promptConfig(name: 'context'), appName: 'shopware'),
        ]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerPrompt');

        $this->loader->load($registry);
    }

    public function testSkipsPromptWhenAppHasNoSecret(): void
    {
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->promptConfig(), appHasSecret: false)]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerPrompt');

        $this->loader->load($registry);
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
