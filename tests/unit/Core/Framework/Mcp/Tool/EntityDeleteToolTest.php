<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Mcp\Tool\EntityDeleteTool;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxDefinition;

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

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityDeleteTool($registry, $contextProvider, static::createStub(Connection::class));
        $result = ($tool)('product', '["abc123"]');

        static::assertTrue($result->isError());
        static::assertStringContainsString('product:delete', $result->error->message);
    }

    public function testReturnsErrorWhenEntityNotFound(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(false);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new EntityDeleteTool($registry, $contextProvider, static::createStub(Connection::class));
        $result = ($tool)('unknown_entity', '["abc"]');

        static::assertTrue($result->isError());
        static::assertStringContainsString('unknown_entity', $result->error->message);
        static::assertSame(McpToolError::INVALID_ARGUMENTS, $result->error->code);
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
        $result = ($tool)('product', 'id1, id2, id3', false);

        static::assertFalse($result->isError());
    }

    public function testReturnsErrorForEmptyIds(): void
    {
        $tool = $this->createTool();
        $result = ($tool)('product', '[]');

        static::assertTrue($result->isError());
        static::assertSame('No valid IDs provided.', $result->error->message);
    }

    public function testReturnsErrorForBlankCommaString(): void
    {
        $tool = $this->createTool();
        $result = ($tool)('product', ', , ');

        static::assertTrue($result->isError());
        static::assertSame('No valid IDs provided.', $result->error->message);
    }

    public function testDryRunRollsBackAndReturnsDeleteResult(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('rollBack');

        $writeResult = new EntityWriteResult('abc', [], 'product', EntityWriteResult::OPERATION_DELETE);
        $writtenEvent = new EntityDeletedEvent('product', [$writeResult], Context::createDefaultContext());
        $events = static::createStub(EntityWrittenContainerEvent::class);
        $events->method('getEvents')->willReturn(new NestedEventCollection([$writtenEvent]));

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('delete')->willReturn($events);

        $tool = $this->createTool($repository, $connection);
        $result = ($tool)('product', '["abc"]', true);

        static::assertFalse($result->isError());
        static::assertTrue($result->meta['dryRun']);
        static::assertSame('product', $result->data[0]['entity']);
        static::assertSame(['abc'], $result->data[0]['ids']);
    }

    public function testReportsTheLinkedEntitiesOfAMappingDeleteAsUpdatedNotDeleted(): void
    {
        $context = Context::createDefaultContext();
        $key = ['productId' => '0198c0de000070008000000000000001', 'categoryId' => '0198c0de000070008000000000000002'];
        $events = static::createStub(EntityWrittenContainerEvent::class);
        $events->method('getEvents')->willReturn(new NestedEventCollection([
            new EntityDeletedEvent('product_category', [new EntityWriteResult($key, [], 'product_category', EntityWriteResult::OPERATION_DELETE)], $context),
            new EntityWrittenEvent('product', [new EntityWriteResult('0198c0de000070008000000000000001', [], 'product', EntityWriteResult::OPERATION_UPDATE)], $context),
            new EntityWrittenEvent('category', [new EntityWriteResult('0198c0de000070008000000000000002', [], 'category', EntityWriteResult::OPERATION_UPDATE)], $context),
        ]));

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('delete')->willReturn($events);

        $tool = $this->createTool($repository, definition: new ProductCategoryDefinition());
        $result = ($tool)('product_category', '[{"productId":"0198c0de000070008000000000000001","categoryId":"0198c0de000070008000000000000002"}]', false);

        static::assertFalse($result->isError());
        static::assertSame(
            [['product_category', 'delete'], ['product', 'update'], ['category', 'update']],
            array_map(static fn (array $row): array => [$row['entity'], $row['operation']], $result->data),
        );
    }

    public function testDryRunReturnsErrorWhenDeleteThrows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('rollBack');

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('delete')->willThrowException(new \RuntimeException('FK constraint'));

        $tool = $this->createTool($repository, $connection);
        $result = ($tool)('product', '["abc"]', true);

        static::assertTrue($result->isError());
        static::assertSame('FK constraint', $result->error->message);
    }

    public function testRealDeleteDoesNotRollBack(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('beginTransaction');
        $connection->expects($this->never())->method('rollBack');

        $events = static::createStub(EntityWrittenContainerEvent::class);
        $events->method('getEvents')->willReturn(new NestedEventCollection());

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('delete')->willReturn($events);

        $tool = $this->createTool($repository, $connection);
        $result = ($tool)('product', '["abc123"]', false);

        static::assertFalse($result->isError());
        static::assertFalse($result->meta['dryRun']);
    }

    public function testIgnoresNonStringIdsForSinglePrimaryKey(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('delete')
            ->with([['id' => 'id1']])
            ->willReturn($this->emptyEvents());

        $tool = $this->createTool($repository);
        $result = ($tool)('tax', '[{"id":"nested"}, 42, "id1"]', false);

        static::assertFalse($result->isError());
    }

    public function testDeletesMappingRowByCompositeKeyAndFillsVersionFields(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('delete')
            ->with([[
                'productId' => '0198c0de000070008000000000000001',
                'categoryId' => '0198c0de000070008000000000000002',
                'productVersionId' => Defaults::LIVE_VERSION,
                'categoryVersionId' => Defaults::LIVE_VERSION,
            ]])
            ->willReturn($this->emptyEvents());

        $tool = $this->createTool($repository, definition: new ProductCategoryDefinition());
        $result = ($tool)(
            'product_category',
            '[{"productId":"0198c0de000070008000000000000001","categoryId":"0198c0de000070008000000000000002"}]',
            false,
        );

        static::assertFalse($result->isError());
    }

    public function testKeepsVersionFieldsTheCallerNames(): void
    {
        $draft = Uuid::randomHex();
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('delete')
            ->with([[
                'productId' => '0198c0de000070008000000000000001',
                'categoryId' => '0198c0de000070008000000000000002',
                'productVersionId' => $draft,
                'categoryVersionId' => Defaults::LIVE_VERSION,
            ]])
            ->willReturn($this->emptyEvents());

        $tool = $this->createTool($repository, definition: new ProductCategoryDefinition(), context: $this->versionedContext($draft));
        $result = ($tool)(
            'product_category',
            \sprintf('[{"productId":"0198c0de000070008000000000000001","categoryId":"0198c0de000070008000000000000002","productVersionId":"%s","categoryVersionId":"%s"}]', $draft, Defaults::LIVE_VERSION),
            false,
        );

        static::assertFalse($result->isError());
    }

    public function testRefusesToGuessVersionFieldsOutsideTheLiveVersion(): void
    {
        $draft = Uuid::randomHex();
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('delete');

        $tool = $this->createTool($repository, definition: new ProductCategoryDefinition(), context: $this->versionedContext($draft));
        $result = ($tool)('product_category', '[{"productId":"0198c0de000070008000000000000001","categoryId":"0198c0de000070008000000000000002"}]', false);

        static::assertTrue($result->isError());
        static::assertSame(
            \sprintf('This request runs in version %s. Name every version field (productVersionId, categoryVersionId) in each ids object, for example the live version %s for a side that is not being edited.', $draft, Defaults::LIVE_VERSION),
            $result->error->message,
        );
    }

    public function testRejectsAVersionFieldThatIsNotAUuid(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('delete');

        $tool = $this->createTool($repository, definition: new ProductCategoryDefinition());
        $result = ($tool)(
            'product_category',
            '[{"productId":"0198c0de000070008000000000000001","categoryId":"0198c0de000070008000000000000002","productVersionId":"not-a-version"}]',
            false,
        );

        static::assertTrue($result->isError());
        static::assertSame('"productVersionId" must be a version UUID.', $result->error->message);
    }

    public function testDeletesMappingRowByCompositeKeyInTheLiveVersionWhenNamedExplicitly(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('delete')->willReturn($this->emptyEvents());

        $tool = $this->createTool($repository, definition: new ProductCategoryDefinition());
        $result = ($tool)(
            'product_category',
            \sprintf('[{"productId":"0198c0de000070008000000000000001","categoryId":"0198c0de000070008000000000000002","productVersionId":"%s"}]', Defaults::LIVE_VERSION),
            false,
        );

        static::assertFalse($result->isError());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCompositeKeyProvider(): iterable
    {
        yield 'plain id list' => ['["0198c0de000070008000000000000001"]'];
        yield 'comma-separated ids' => ['0198c0de000070008000000000000001, 0198c0de000070008000000000000002'];
        yield 'empty list' => ['[]'];
        yield 'single object instead of list' => ['{"productId":"0198c0de000070008000000000000001","categoryId":"0198c0de000070008000000000000002"}'];
        yield 'missing key field' => ['[{"productId":"0198c0de000070008000000000000001"}]'];
        yield 'empty key value' => ['[{"productId":"0198c0de000070008000000000000001","categoryId":""}]'];
        yield 'non-string key value' => ['[{"productId":"0198c0de000070008000000000000001","categoryId":42}]'];
    }

    public function testRejectsACompositeKeyThatIsNotAUuid(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('delete');

        $tool = $this->createTool($repository, definition: new ProductCategoryDefinition());
        $result = ($tool)('product_category', '[{"productId":"0198c0de000070008000000000000001","categoryId":"not-a-uuid"}]', false);

        static::assertTrue($result->isError());
        static::assertStringStartsWith('"categoryId" must be a UUID.', $result->error->message);
    }

    public function testFailsWhenNoneOfTheRowsExists(): void
    {
        // A link that isn't there must not read as removed, like the Admin API's 404.
        $key = ['productId' => '0198c0de000070008000000000000001', 'categoryId' => '0198c0de000070008000000000000002'];
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('delete')->willReturn($this->eventsWithNotFound([$key]));

        $tool = $this->createTool($repository, definition: new ProductCategoryDefinition());
        $result = ($tool)('product_category', json_encode([$key], \JSON_THROW_ON_ERROR), false);

        static::assertTrue($result->isError());
        static::assertStringContainsString('none of these rows exist', $result->error->message);
        static::assertStringContainsString('0198c0de000070008000000000000002', $result->error->message);
    }

    public function testReportsTheRowsThatDidNotExistWhenSomeDid(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('delete')->willReturn($this->eventsWithNotFound(['0198c0de000070008000000000000002']));

        $tool = $this->createTool($repository);
        $result = ($tool)('tax', '["0198c0de000070008000000000000001","0198c0de000070008000000000000002"]', false);

        static::assertFalse($result->isError());
        static::assertSame(['0198c0de000070008000000000000002'], $result->meta['notFound']);
    }

    #[DataProvider('invalidCompositeKeyProvider')]
    public function testRejectsInvalidCompositeKeys(string $ids): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('delete');

        $tool = $this->createTool($repository, definition: new ProductCategoryDefinition());
        $result = ($tool)('product_category', $ids, false);

        static::assertTrue($result->isError());
        static::assertSame(
            'Entity "product_category" has a composite primary key. Pass ids as a JSON array of objects with productId and categoryId, e.g. [{"productId":"...","categoryId":"..."}].',
            $result->error->message,
        );
    }

    /**
     * @param list<string|array<string, string>> $missingKeys
     */
    private function eventsWithNotFound(array $missingKeys): EntityWrittenContainerEvent
    {
        $events = static::createStub(EntityWrittenContainerEvent::class);
        $events->method('getEvents')->willReturn(new NestedEventCollection());
        $events->method('getErrors')->willReturn(['tax' => array_map(
            static fn (string|array $key): EntityWriteResult => new EntityWriteResult($key, [], 'tax', EntityWriteResult::OPERATION_DELETE),
            $missingKeys,
        )]);

        return $events;
    }

    /**
     * @param (MockObject&EntityRepository<EntityCollection<Entity>>)|null $repository
     */
    private function createTool(?EntityRepository $repository = null, ?Connection $connection = null, ?EntityDefinition $definition = null, ?Context $context = null): EntityDeleteTool
    {
        if ($repository === null) {
            $repository = static::createStub(EntityRepository::class);
            $events = static::createStub(EntityWrittenContainerEvent::class);
            $events->method('getEvents')->willReturn(new NestedEventCollection());
            $repository->method('delete')->willReturn($events);
        }

        $connection ??= static::createStub(Connection::class);

        $definition ??= new TaxDefinition();
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getRepository')->willReturn($repository);
        $registry->method('getByEntityName')->willReturn($definition);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context ?? Context::createDefaultContext());

        return new EntityDeleteTool($registry, $contextProvider, $connection);
    }

    private function versionedContext(string $versionId): Context
    {
        return Context::createDefaultContext()->createWithVersionId($versionId);
    }

    private function emptyEvents(): EntityWrittenContainerEvent
    {
        $events = static::createStub(EntityWrittenContainerEvent::class);
        $events->method('getEvents')->willReturn(new NestedEventCollection());

        return $events;
    }
}
