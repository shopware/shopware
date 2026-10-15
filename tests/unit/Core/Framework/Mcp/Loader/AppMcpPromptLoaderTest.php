<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Loader;

use Doctrine\DBAL\Connection;
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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Loader\AppMcpCapabilityExecutor;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPromptLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppMcpPromptLoader::class)]
class AppMcpPromptLoaderTest extends TestCase
{
    private Connection&Stub $connection;

    private AppMcpCapabilityExecutor&Stub $executor;

    private AppMcpPromptLoader $loader;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->executor = static::createStub(AppMcpCapabilityExecutor::class);
        $this->loader = new AppMcpPromptLoader($this->connection, $this->executor, new NullLogger());
    }

    public function testLoadWithOnePromptRegistersPromptWithCorrectName(): void
    {
        $promptRow = [
            'name' => 'order-context',
            'url' => 'https://app.example.com/mcp/prompt/order-context',
            'app_name' => 'my-app',
            'app_secret' => 'test-secret',
            'label' => 'Order Context',
            'description' => 'Context for order management',
        ];

        $this->connection->method('fetchAllAssociative')
            ->willReturn([$promptRow]);

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
            );

        $this->loader->load($registry);
    }

    public function testTitleIsNullWhenLabelIsEmpty(): void
    {
        $promptRow = [
            'name' => 'order-context',
            'url' => 'https://app.example.com/mcp/prompt/order-context',
            'app_name' => 'my-app',
            'app_secret' => 'secret',
            'label' => '',
            'description' => 'Context for order management',
        ];

        $this->connection->method('fetchAllAssociative')->willReturn([$promptRow]);

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
            );

        $this->loader->load($registry);
    }

    public function testRegisteredCallbackInvokesExecutorWithEmptyArguments(): void
    {
        $promptRow = [
            'name' => 'order-context',
            'url' => 'https://app.example.com/mcp/prompt/order-context',
            'app_name' => 'my-app',
            'app_secret' => 'test-secret',
            'label' => 'Order Context',
            'description' => 'Context for orders',
        ];

        $this->connection->method('fetchAllAssociative')->willReturn([$promptRow]);

        $executor = $this->createMock(AppMcpCapabilityExecutor::class);
        $executor->expects($this->once())
            ->method('execute')
            ->with('my-app-order-context', 'test-secret', 'https://app.example.com/mcp/prompt/order-context', [])
            ->willReturn('{"messages":[]}');
        $loader = new AppMcpPromptLoader($this->connection, $executor, new NullLogger());

        $capturedCallback = null;
        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerPrompt')
            ->willReturnCallback(function (Prompt $prompt, callable $callback) use (&$capturedCallback): PromptReference {
                $capturedCallback = $callback;

                return static::createStub(PromptReference::class);
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
}
