<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
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
    public function testDeniesAccessWithoutDeletePermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions(['product:read']);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->expects($this->never())->method('getRepository');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityDeleteTool($registry, $contextProvider, $this->createMock(Connection::class));
        $result = $this->decode(($tool)('product', '["abc123"]'));

        static::assertFalse($result['success']);
        static::assertStringContainsString('product:delete', $result['error']);
    }

    public function testReturnsErrorWhenEntityNotFound(): void
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(false);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new EntityDeleteTool($registry, $contextProvider, $this->createMock(Connection::class));
        $result = $this->decode(($tool)('unknown_entity', '["abc"]'));

        static::assertFalse($result['success']);
        static::assertStringContainsString('unknown_entity', $result['error']);
    }

    public function testParsesCommaSeparatedIds(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('delete')
            ->with(static::callback(function (array $payload): bool {
                return $payload === [['id' => 'id1'], ['id' => 'id2'], ['id' => 'id3']];
            }));

        $tool = $this->createTool($repository);
        $result = $this->decode(($tool)('product', 'id1, id2, id3', false));

        static::assertTrue($result['success']);
    }

    public function testReturnsErrorForEmptyIds(): void
    {
        $tool = $this->createTool();
        $result = $this->decode(($tool)('product', '[]'));

        static::assertFalse($result['success']);
        static::assertSame('No valid IDs provided.', $result['error']);
    }

    public function testReturnsErrorForBlankCommaString(): void
    {
        $tool = $this->createTool();
        $result = $this->decode(($tool)('product', ', , '));

        static::assertFalse($result['success']);
        static::assertSame('No valid IDs provided.', $result['error']);
    }

    public function testDryRunRollsBackAndReturnsDeleteResult(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('rollBack');

        $writeResult = new EntityWriteResult('abc', [], 'product', EntityWriteResult::OPERATION_DELETE);
        $writtenEvent = new EntityWrittenEvent('product', [$writeResult], Context::createDefaultContext());
        $events = $this->createMock(EntityWrittenContainerEvent::class);
        $events->method('getEvents')->willReturn(new NestedEventCollection([$writtenEvent]));

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('delete')->willReturn($events);

        $tool = $this->createTool($repository, $connection);
        $result = $this->decode(($tool)('product', '["abc"]', true));

        static::assertTrue($result['success']);
        static::assertTrue($result['_meta']['dryRun']);
        static::assertSame('product', $result['data'][0]['entity']);
        static::assertSame(['abc'], $result['data'][0]['ids']);
    }

    public function testDryRunReturnsErrorWhenDeleteThrows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('rollBack');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('delete')->willThrowException(new \RuntimeException('FK constraint'));

        $tool = $this->createTool($repository, $connection);
        $result = $this->decode(($tool)('product', '["abc"]', true));

        static::assertFalse($result['success']);
        static::assertSame('FK constraint', $result['error']);
    }

    public function testRealDeleteDoesNotRollBack(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('beginTransaction');
        $connection->expects($this->never())->method('rollBack');

        $events = $this->createMock(EntityWrittenContainerEvent::class);
        $events->method('getEvents')->willReturn(new NestedEventCollection());

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('delete')->willReturn($events);

        $tool = $this->createTool($repository, $connection);
        $result = $this->decode(($tool)('product', '["abc123"]', false));

        static::assertTrue($result['success']);
        static::assertFalse($result['_meta']['dryRun']);
    }

    /**
     * @param (MockObject&EntityRepository<EntityCollection<Entity>>)|null $repository
     */
    private function createTool(?EntityRepository $repository = null, ?Connection $connection = null): EntityDeleteTool
    {
        if ($repository === null) {
            $repository = $this->createMock(EntityRepository::class);
            $events = $this->createMock(EntityWrittenContainerEvent::class);
            $events->method('getEvents')->willReturn(new NestedEventCollection());
            $repository->method('delete')->willReturn($events);
        }

        $connection ??= $this->createMock(Connection::class);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getRepository')->willReturn($repository);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        return new EntityDeleteTool($registry, $contextProvider, $connection);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        return json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
    }
}
