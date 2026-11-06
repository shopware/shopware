<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Loader;

use Mcp\Capability\Formatter\PromptResultFormatter;
use Mcp\Capability\Registry\PromptReference;
use Mcp\Capability\RegistryInterface;
use Mcp\Exception\PromptGetException;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\Prompt;
use Mcp\Server\RequestContext;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\App\Mcp\Feature\McpPromptConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Loader\AppMcpCapabilityExecutor;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPromptLoader;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppMcpPromptLoader::class)]
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
            ->willReturn('[]');

        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->promptConfig())]);
        $loader = new AppMcpPromptLoader($this->storage, $executor, $this->localeProvider, new NullLogger());

        static::assertSame([], $this->getPrompt($loader));
    }

    public function testTheMessagesOfTheAppAreAcceptedByTheMcpSdk(): void
    {
        $this->executor->method('execute')->willReturn(Json::encode([
            ['role' => 'user', 'content' => 'You are working with ERP-synced data.'],
            ['role' => 'assistant', 'content' => ['type' => 'text', 'text' => 'Understood.']],
        ]));
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->promptConfig())]);

        $messages = (new PromptResultFormatter())->format($this->getPrompt($this->loader));

        static::assertEquals([
            new PromptMessage(Role::User, new TextContent('You are working with ERP-synced data.')),
            new PromptMessage(Role::Assistant, new TextContent('Understood.')),
        ], $messages);
    }

    #[DataProvider('invalidResponseProvider')]
    public function testAnUnusableResponseFailsWithAMessageForTheClient(string $body, string $expectedMessage): void
    {
        $this->executor->method('execute')->willReturn($body);
        $this->storage->method('forActiveApps')->willReturn([$this->feature($this->promptConfig())]);

        $this->expectExceptionObject(new PromptGetException($expectedMessage));

        $this->getPrompt($this->loader);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidResponseProvider(): iterable
    {
        yield 'failed app call' => [
            '{"success":false,"error":"App capability \\"my-app-order-context\\" execution failed: timeout"}',
            'App capability "my-app-order-context" execution failed: timeout',
        ];
        yield 'failure without error text' => ['{"success":false}', 'App prompt "my-app-order-context" failed.'];
        yield 'not JSON' => ['You are working with ERP data.', 'App prompt "my-app-order-context" returned a response that is not valid JSON.'];
        yield 'object instead of array' => ['{"role":"user","content":"Hi"}', 'App prompt "my-app-order-context" must respond with a JSON array of messages.'];
        yield 'scalar' => ['"Hi"', 'App prompt "my-app-order-context" must respond with a JSON array of messages.'];
    }

    /**
     * @return array<mixed>
     */
    private function getPrompt(AppMcpPromptLoader $loader): array
    {
        $result = $this->registeredHandler($loader)(new RequestContext(static::createStub(SessionInterface::class), static::createStub(Request::class)));
        static::assertIsArray($result);

        return $result;
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
