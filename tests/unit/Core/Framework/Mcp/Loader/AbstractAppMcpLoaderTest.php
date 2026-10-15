<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Loader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Prompt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Loader\AbstractAppMcpLoader;
use Shopware\Core\Framework\Mcp\Loader\AppMcpCapabilityExecutor;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPromptLoader;

/**
 * @internal
 *
 * Exercises the shared load flow through AppMcpPromptLoader, the lightest concrete subclass.
 */
#[Package('framework')]
#[CoversClass(AbstractAppMcpLoader::class)]
class AbstractAppMcpLoaderTest extends TestCase
{
    private Connection&Stub $connection;

    private AppMcpPromptLoader $loader;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->loader = new AppMcpPromptLoader(
            $this->connection,
            static::createStub(AppMcpCapabilityExecutor::class),
            new NullLogger(),
        );
    }

    public function testLoadWithDBALExceptionRegistersNoPrompts(): void
    {
        $exception = new class('DB error') extends \Exception implements DBALException {};

        $this->connection->method('fetchAllAssociative')
            ->willThrowException($exception);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerPrompt');

        $this->loader->load($registry);
    }

    public function testDescriptionFallsBackToPromptNameWhenNoLabelOrDescription(): void
    {
        $promptRow = [
            'name' => 'mystery-prompt',
            'url' => 'https://app.example.com/mcp/prompt/mystery',
            'app_name' => 'my-app',
            'app_secret' => 'secret',
            'label' => null,
            'description' => null,
        ];

        $this->connection->method('fetchAllAssociative')->willReturn([$promptRow]);

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
            );

        $this->loader->load($registry);
    }

    public function testLoadWithEmptyResultRegistersNoPrompts(): void
    {
        $this->connection->method('fetchAllAssociative')
            ->willReturn([]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerPrompt');

        $this->loader->load($registry);
    }

    public function testPromptWithReservedShopwarePrefixIsSkipped(): void
    {
        $promptRow = [
            'name' => 'context',
            'url' => 'https://app.example.com/mcp/prompt/context',
            'app_name' => 'shopware',
            'app_secret' => 'secret',
            'label' => null,
            'description' => null,
        ];

        $this->connection->method('fetchAllAssociative')->willReturn([$promptRow]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerPrompt');

        $this->loader->load($registry);
    }
}
