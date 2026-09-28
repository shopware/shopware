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
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\EntityDeleteTool;
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
        $result = $this->decode(($tool)('product', '["abc123"]'));

        static::assertFalse($result['success']);
        static::assertStringContainsString('product:delete', $result['error']);
    }

    public function testReturnsErrorWhenEntityNotFound(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(false);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new EntityDeleteTool($registry, $contextProvider, static::createStub(Connection::class));
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
        $events = static::createStub(EntityWrittenContainerEvent::class);
        $events->method('getEvents')->willReturn(new NestedEventCollection([$writtenEvent]));

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('delete')->willReturn($events);

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
        $repository->expects($this->once())->method('delete')->willThrowException(new \RuntimeException('FK constraint'));

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

        $events = static::createStub(EntityWrittenContainerEvent::class);
        $events->method('getEvents')->willReturn(new NestedEventCollection());

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('delete')->willReturn($events);

        $tool = $this->createTool($repository, $connection);
        $result = $this->decode(($tool)('product', '["abc123"]', false));

        static::assertTrue($result['success']);
        static::assertFalse($result['_meta']['dryRun']);
    }

    public function testIgnoresNonStringIdsForSinglePrimaryKey(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('delete')
            ->with([['id' => 'id1']])
            ->willReturn($this->emptyEvents());

        $tool = $this->createTool($repository);
        $result = $this->decode(($tool)('tax', '[{"id":"nested"}, 42, "id1"]', false));

        static::assertTrue($result['success']);
    }

    public function testDeletesMappingRowByCompositeKeyAndFillsVersionFields(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('delete')
            ->with([[
                'productId' => 'product-1',
                'categoryId' => 'category-1',
                'productVersionId' => Defaults::LIVE_VERSION,
                'categoryVersionId' => Defaults::LIVE_VERSION,
            ]])
            ->willReturn($this->emptyEvents());

        $tool = $this->createTool($repository, definition: new ProductCategoryDefinition());
        $result = $this->decode(($tool)(
            'product_category',
            '[{"productId":"product-1","categoryId":"category-1","productVersionId":"ignored"}]',
            false,
        ));

        static::assertTrue($result['success']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCompositeKeyProvider(): iterable
    {
        yield 'plain id list' => ['["product-1"]'];
        yield 'comma-separated ids' => ['product-1, category-1'];
        yield 'empty list' => ['[]'];
        yield 'single object instead of list' => ['{"productId":"product-1","categoryId":"category-1"}'];
        yield 'missing key field' => ['[{"productId":"product-1"}]'];
        yield 'empty key value' => ['[{"productId":"product-1","categoryId":""}]'];
        yield 'non-string key value' => ['[{"productId":"product-1","categoryId":42}]'];
    }

    #[DataProvider('invalidCompositeKeyProvider')]
    public function testRejectsInvalidCompositeKeys(string $ids): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('delete');

        $tool = $this->createTool($repository, definition: new ProductCategoryDefinition());
        $result = $this->decode(($tool)('product_category', $ids, false));

        static::assertFalse($result['success']);
        static::assertSame(
            'Entity "product_category" has a composite primary key. Pass ids as a JSON array of objects with productId and categoryId, e.g. [{"productId":"...","categoryId":"..."}].',
            $result['error'],
        );
    }

    /**
     * @param (MockObject&EntityRepository<EntityCollection<Entity>>)|null $repository
     */
    private function createTool(?EntityRepository $repository = null, ?Connection $connection = null, ?EntityDefinition $definition = null): EntityDeleteTool
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
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        return new EntityDeleteTool($registry, $contextProvider, $connection);
    }

    private function emptyEvents(): EntityWrittenContainerEvent
    {
        $events = static::createStub(EntityWrittenContainerEvent::class);
        $events->method('getEvents')->willReturn(new NestedEventCollection());

        return $events;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        return json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
    }
}
