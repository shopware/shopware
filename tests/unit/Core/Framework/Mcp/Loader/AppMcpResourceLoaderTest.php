<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Loader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\Resource;
use Mcp\Server\RequestContext;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Loader\AbstractAppMcpLoader;
use Shopware\Core\Framework\Mcp\Loader\AppMcpCapabilityExecutor;
use Shopware\Core\Framework\Mcp\Loader\AppMcpResourceLoader;

/**
 * @internal
 */
#[CoversClass(AppMcpResourceLoader::class)]
#[CoversClass(AbstractAppMcpLoader::class)]
#[Package('framework')]
class AppMcpResourceLoaderTest extends TestCase
{
    private Connection&MockObject $connection;

    private AppMcpCapabilityExecutor&MockObject $executor;

    private AppMcpResourceLoader $loader;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->executor = $this->createMock(AppMcpCapabilityExecutor::class);
        $this->loader = new AppMcpResourceLoader($this->connection, $this->executor);
    }

    public function testLoadWithDBALExceptionRegistersNoResources(): void
    {
        $exception = new class('DB error') extends \Exception implements DBALException {};

        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willThrowException($exception);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerResource');

        $this->loader->load($registry);
    }

    public function testLoadWithOneResourceRegistersResourceWithCorrectProperties(): void
    {
        $resourceRow = [
            'name' => 'order-stats',
            'uri' => 'app-example://order-stats',
            'url' => 'https://app.example.com/mcp/resource/order-stats',
            'mime_type' => 'application/json',
            'app_name' => 'my-app',
            'app_secret' => 'test-secret',
            'label' => 'Order Stats',
            'description' => 'Live order statistics',
        ];

        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([$resourceRow]);

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
        $resourceRow = [
            'name' => 'plain-resource',
            'uri' => 'app://plain',
            'url' => 'https://app.example.com/mcp/resource/plain',
            'mime_type' => null,
            'app_name' => 'my-app',
            'app_secret' => 'secret',
            'label' => 'Plain Resource',
            'description' => null,
        ];

        $this->connection->method('fetchAllAssociative')->willReturn([$resourceRow]);

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
        $resourceRow = [
            'name' => 'mystery-resource',
            'uri' => 'app://mystery',
            'url' => 'https://app.example.com/mcp/resource/mystery',
            'mime_type' => null,
            'app_name' => 'my-app',
            'app_secret' => 'secret',
            'label' => null,
            'description' => null,
        ];

        $this->connection->method('fetchAllAssociative')->willReturn([$resourceRow]);

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
        $resourceRow = [
            'name' => 'order-stats',
            'uri' => 'app-example://order-stats',
            'url' => 'https://app.example.com/mcp/resource/order-stats',
            'mime_type' => 'application/json',
            'app_name' => 'my-app',
            'app_secret' => 'test-secret',
            'label' => 'Order Stats',
            'description' => 'Live stats',
        ];

        $this->connection->method('fetchAllAssociative')->willReturn([$resourceRow]);

        $this->executor->expects($this->once())
            ->method('execute')
            ->with(
                'my-app-order-stats',
                'test-secret',
                'https://app.example.com/mcp/resource/order-stats',
                ['uri' => 'app-example://order-stats'],
            )
            ->willReturn('{"contents":[]}');

        $capturedCallback = null;
        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('registerResource')
            ->willReturnCallback(function (Resource $resource, callable $callback) use (&$capturedCallback): void {
                $capturedCallback = $callback;
            });

        $this->loader->load($registry);

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
        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerResource');

        $this->loader->load($registry);
    }

    public function testResourceWithReservedShopwarePrefixIsSkipped(): void
    {
        $resourceRow = [
            'name' => 'entities',
            'uri' => 'shopware://entities',
            'url' => 'https://app.example.com/mcp/resource/entities',
            'app_name' => 'shopware',
            'app_secret' => 'secret',
            'label' => null,
            'description' => null,
            'mime_type' => null,
        ];

        $this->connection->method('fetchAllAssociative')->willReturn([$resourceRow]);

        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->never())->method('registerResource');

        $this->loader->load($registry);
    }
}
