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
use Shopware\Core\Framework\Mcp\Tool\EntityUpsertTool;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityUpsertTool::class)]
class EntityUpsertToolTest extends TestCase
{
    public function testDeniesAccessWithoutCreatePermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions(['product:read']);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->expects($this->never())->method('getRepository');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityUpsertTool($registry, $contextProvider, $this->createMock(Connection::class));
        $result = $this->decode(($tool)('product', '{"name": "Test"}'));

        static::assertFalse($result['success']);
        static::assertStringContainsString('product:create', $result['error']);
    }

    public function testDeniesAccessWithoutUpdatePermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions(['product:read', 'product:create']);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->expects($this->never())->method('getRepository');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityUpsertTool($registry, $contextProvider, $this->createMock(Connection::class));
        // Payload with an id triggers an update operation, which requires :update privilege
        $result = $this->decode(($tool)('product', '{"id": "' . Defaults::CURRENCY . '", "name": "Test"}'));

        static::assertFalse($result['success']);
        static::assertStringContainsString('product:update', $result['error']);
    }

    public function testAllowsCreateOnlyWithoutUpdatePermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions(['product:read', 'product:create']);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $events = $this->createMock(EntityWrittenContainerEvent::class);
        $events->method('getEvents')->willReturn(new NestedEventCollection([]));

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('upsert')->willReturn($events);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getRepository')->willReturn($repository);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityUpsertTool($registry, $contextProvider, $this->createMock(Connection::class));
        // Payload without id — only create privilege needed
        $result = $this->decode(($tool)('product', '{"name": "Test"}', false));

        static::assertTrue($result['success']);
    }

    public function testAllowsUpdateOnlyWithoutCreatePermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions(['product:read', 'product:update']);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $events = $this->createMock(EntityWrittenContainerEvent::class);
        $events->method('getEvents')->willReturn(new NestedEventCollection([]));

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('upsert')->willReturn($events);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getRepository')->willReturn($repository);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityUpsertTool($registry, $contextProvider, $this->createMock(Connection::class));
        // Payload with id — only update privilege needed
        $result = $this->decode(($tool)('product', '{"id": "' . Defaults::CURRENCY . '", "name": "Test"}', false));

        static::assertTrue($result['success']);
    }

    public function testEmptyPayloadRequiresCreatePrivilege(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityUpsertTool($registry, $contextProvider, $this->createMock(Connection::class));
        $result = $this->decode(($tool)('product', '[]'));

        static::assertFalse($result['success']);
        static::assertStringContainsString('product:create', $result['error']);
    }

    public function testReturnsErrorForNonArrayPayload(): void
    {
        $tool = $this->createTool();
        $result = $this->decode(($tool)('product', '"just a string"'));

        static::assertFalse($result['success']);
        static::assertStringContainsString('"payload"', $result['error']);
        static::assertStringContainsString('must be a JSON object or array', $result['error']);
    }

    public function testNormalizesObjectPayloadToList(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('upsert')
            ->with(static::callback(fn (array $data): bool => \array_is_list($data) && \count($data) === 1));

        $tool = $this->createTool($repository);
        $result = $this->decode(($tool)('product', '{"name": "Test"}', false));

        static::assertTrue($result['success']);
    }

    public function testDryRunRollsBackAndReturnsWriteResult(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('rollBack');

        $writeResult = new EntityWriteResult('abc', [], 'product', EntityWriteResult::OPERATION_INSERT);
        $writtenEvent = new EntityWrittenEvent('product', [$writeResult], Context::createDefaultContext());
        $events = $this->createMock(EntityWrittenContainerEvent::class);
        $events->method('getEvents')->willReturn(new NestedEventCollection([$writtenEvent]));

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('upsert')->willReturn($events);

        $tool = $this->createTool($repository, $connection);
        $result = $this->decode(($tool)('product', '[{"name": "Test"}]', true));

        static::assertTrue($result['success']);
        static::assertTrue($result['_meta']['dryRun']);
        static::assertSame('product', $result['data'][0]['entity']);
        static::assertSame(['abc'], $result['data'][0]['ids']);
    }

    public function testDryRunReturnsErrorWhenUpsertThrows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('rollBack');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('upsert')->willThrowException(new \RuntimeException('Constraint violation'));

        $tool = $this->createTool($repository, $connection);
        $result = $this->decode(($tool)('product', '[{"name": "Test"}]', true));

        static::assertFalse($result['success']);
        static::assertSame('Constraint violation', $result['error']);
    }

    public function testRealUpsertDoesNotRollBack(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('beginTransaction');
        $connection->expects($this->never())->method('rollBack');

        $events = $this->createMock(EntityWrittenContainerEvent::class);
        $events->method('getEvents')->willReturn(new NestedEventCollection());

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('upsert')->willReturn($events);

        $tool = $this->createTool($repository, $connection);
        $result = $this->decode(($tool)('product', '{"name": "Test"}', false));

        static::assertTrue($result['success']);
        static::assertFalse($result['_meta']['dryRun']);
    }

    /**
     * @param (MockObject&EntityRepository<EntityCollection<Entity>>)|null $repository
     */
    private function createTool(?EntityRepository $repository = null, ?Connection $connection = null): EntityUpsertTool
    {
        if ($repository === null) {
            $repository = $this->createMock(EntityRepository::class);
            $events = $this->createMock(EntityWrittenContainerEvent::class);
            $events->method('getEvents')->willReturn(new NestedEventCollection());
            $repository->method('upsert')->willReturn($events);
        }

        $connection ??= $this->createMock(Connection::class);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getRepository')->willReturn($repository);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        return new EntityUpsertTool($registry, $contextProvider, $connection);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        return json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
    }
}
