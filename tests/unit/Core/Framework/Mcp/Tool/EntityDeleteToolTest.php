<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\EntityDeleteTool;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityDeleteTool::class)]
class EntityDeleteToolTest extends TestCase
{
    public function testDryRunRollsBack(): void
    {
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('rollBack');

        $events = $this->createMock(EntityWrittenContainerEvent::class);
        $events->method('getEvents')->willReturn(new NestedEventCollection());

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('delete')->willReturn($events);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->with('product')->willReturn($repository);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityDeleteTool($registry, $contextProvider, $connection);
        $output = ($tool)('product', '["abc123"]', true);

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['dryRun']);
        static::assertTrue($data['success']);
    }

    public function testRealDelete(): void
    {
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('beginTransaction');

        $events = $this->createMock(EntityWrittenContainerEvent::class);
        $events->method('getEvents')->willReturn(new NestedEventCollection());

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('delete')->willReturn($events);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($repository);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityDeleteTool($registry, $contextProvider, $connection);
        $output = ($tool)('product', '["abc123"]', false);

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['dryRun']);
        static::assertTrue($data['success']);
    }

    public function testDeniesAccessWithoutDeletePermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions(['product:read']);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->expects($this->never())->method('getRepository');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityDeleteTool($registry, $contextProvider, $this->createMock(Connection::class));
        $output = ($tool)('product', '["abc123"]');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('error', $data);
        static::assertStringContainsString('product:delete', $data['error']);
    }
}
