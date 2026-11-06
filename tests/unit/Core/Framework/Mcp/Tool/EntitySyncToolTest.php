<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncResult;
use Shopware\Core\Framework\Api\Sync\SyncServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;
use Shopware\Core\Framework\Mcp\Tool\EntitySyncTool;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntitySyncTool::class)]
class EntitySyncToolTest extends TestCase
{
    private const PRODUCT_ID = '0198c0de000070008000000000000001';
    private const CATEGORY_ID = '0198c0de000070008000000000000002';

    public function testDryRunIsTheDefaultAndRollsBack(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('rollBack');

        $syncService = $this->createMock(SyncServiceInterface::class);
        $syncService->expects($this->once())
            ->method('sync')
            ->willReturnCallback(static function (array $operations, Context $context): SyncResult {
                // executeWithDryRun() keeps flows from firing during the preview.
                static::assertTrue($context->hasState(Context::SKIP_TRIGGER_FLOW));
                static::assertSame(Context::CRUD_API_SCOPE, $context->getScope());

                return new SyncResult(['tax' => [self::PRODUCT_ID]]);
            });

        $result = $this->createTool($syncService, $connection)('[{"entity":"tax","action":"upsert","payload":[{"name":"Reduced","taxRate":7}]}]');

        static::assertNull($result->error);
        static::assertSame(['written' => ['tax' => [self::PRODUCT_ID]], 'deleted' => [], 'notFound' => []], $result->data);
        static::assertSame(['dryRun' => true, 'operations' => 1, 'indexingBehavior' => 'default', 'skipTriggerFlow' => false], $result->meta);
        static::assertSame('Dry run, nothing was changed. Would be: 1 written, 0 deleted, 0 not found. Call again with dryRun=false to write.', $result->summary);
    }

    public function testWritesWithoutTransactionWhenDryRunIsOff(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('beginTransaction');

        $syncService = $this->createMock(SyncServiceInterface::class);
        $syncService->expects($this->once())
            ->method('sync')
            ->willReturnCallback(static function (array $operations, Context $context): SyncResult {
                static::assertFalse($context->hasState(Context::SKIP_TRIGGER_FLOW));
                static::assertCount(2, $operations);
                static::assertContainsOnlyInstancesOf(SyncOperation::class, $operations);
                static::assertSame('remove-link', $operations[1]->getKey());

                return new SyncResult(['tax' => [self::PRODUCT_ID]], [], ['product_category' => [['productId' => self::PRODUCT_ID, 'categoryId' => self::CATEGORY_ID]]]);
            });

        $result = $this->createTool($syncService, $connection)(
            \sprintf(
                '[{"entity":"tax","action":"upsert","payload":[{"id":"%s","name":"Reduced"}]},{"key":"remove-link","entity":"product_category","action":"delete","payload":[{"productId":"%s","categoryId":"%s"}]}]',
                self::PRODUCT_ID,
                self::PRODUCT_ID,
                self::CATEGORY_ID,
            ),
            false,
        );

        static::assertNull($result->error);
        static::assertFalse($result->meta['dryRun']);
        static::assertSame('Done: 1 written, 1 deleted, 0 not found.', $result->summary);
    }

    public function testAcceptsOperationsKeyedByName(): void
    {
        $syncService = $this->createMock(SyncServiceInterface::class);
        $syncService->expects($this->once())
            ->method('sync')
            ->willReturnCallback(static function (array $operations): SyncResult {
                static::assertInstanceOf(SyncOperation::class, $operations[0]);
                static::assertSame('write-tax', $operations[0]->getKey());

                return new SyncResult([]);
            });

        $result = $this->createTool($syncService)('{"write-tax":{"entity":"tax","action":"upsert","payload":[{"name":"Reduced","taxRate":7}]}}', false);

        static::assertNull($result->error);
    }

    public function testSkipTriggerFlowAndIndexingBehaviorReachTheSyncService(): void
    {
        $context = $this->contextWithPermissions(['tax:create']);

        $syncService = $this->createMock(SyncServiceInterface::class);
        $syncService->expects($this->once())
            ->method('sync')
            ->willReturnCallback(static function (array $operations, Context $context, SyncBehavior $behavior): SyncResult {
                static::assertTrue($context->hasState(Context::SKIP_TRIGGER_FLOW));
                static::assertSame('use-queue-indexing', $behavior->getIndexingBehavior());

                return new SyncResult([]);
            });

        $result = $this->createTool($syncService, context: $context)('[{"entity":"tax","action":"upsert","payload":[{"name":"Reduced"}]}]', false, 'use-queue-indexing', true);

        static::assertNull($result->error);
        static::assertSame('use-queue-indexing', $result->meta['indexingBehavior']);
        static::assertTrue($result->meta['skipTriggerFlow']);
        // The state is set on a copy, not on the context the rest of the request uses.
        static::assertFalse($context->hasState(Context::SKIP_TRIGGER_FLOW));
    }

    public function testDefaultIndexingBehaviorIsNull(): void
    {
        $syncService = $this->createMock(SyncServiceInterface::class);
        $syncService->expects($this->once())
            ->method('sync')
            ->willReturnCallback(static function (array $operations, Context $context, SyncBehavior $behavior): SyncResult {
                static::assertNull($behavior->getIndexingBehavior());

                return new SyncResult([]);
            });

        $this->createTool($syncService)('[{"entity":"tax","action":"upsert","payload":[{"name":"Reduced"}]}]', false);
    }

    #[DataProvider('unsupportedIndexingBehaviors')]
    public function testRejectsUnsupportedIndexingBehavior(string $indexingBehavior): void
    {
        $result = $this->createTool($this->neverSyncs())('[{"entity":"tax","action":"upsert","payload":[{"name":"Reduced"}]}]', false, $indexingBehavior);

        static::assertSame(McpToolError::INVALID_ARGUMENTS, $result->error?->code);
        static::assertStringContainsString(\sprintf('Unsupported indexingBehavior "%s"', $indexingBehavior), $result->error->message);
    }

    public static function unsupportedIndexingBehaviors(): \Generator
    {
        yield 'disable-indexing' => ['disable-indexing'];
        yield 'unknown value' => ['later'];
        yield 'different case' => ['USE-QUEUE-INDEXING'];
    }

    /**
     * @param non-empty-string $expected
     */
    #[DataProvider('invalidOperations')]
    public function testRejectsInvalidOperations(string $operations, string $expected): void
    {
        $result = $this->createTool($this->neverSyncs())($operations, false);

        static::assertSame(McpToolError::INVALID_ARGUMENTS, $result->error?->code);
        static::assertStringContainsString($expected, $result->error->message);
    }

    public static function invalidOperations(): \Generator
    {
        yield 'malformed JSON' => ['[{', 'Invalid JSON for "operations"'];
        yield 'scalar' => ['"tax"', '"operations" must be a non-empty JSON array'];
        yield 'empty list' => ['[]', '"operations" must be a non-empty JSON array'];
        yield 'operation is not an object' => ['["tax"]', 'Operation "0" must be an object'];
        yield 'unknown action' => ['[{"entity":"tax","action":"merge","payload":[{"id":"x"}]}]', 'Missing or invalid "action" argument for operation with key "0"'];
        yield 'missing payload' => ['[{"entity":"tax","action":"upsert"}]', 'Missing "payload"|"criteria" argument for operation with key "0"'];
        yield 'payload rows are not objects' => ['[{"entity":"tax","action":"delete","payload":["0198c0de000070008000000000000001"]}]', 'Operation "0": "payload" must be a list of objects'];
    }

    public function testRejectsUnknownEntity(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $entity): bool => $entity === 'tax');

        $tool = new EntitySyncTool($this->neverSyncs(), $registry, $this->contextProvider(Context::createDefaultContext()), static::createStub(Connection::class));
        $result = $tool('[{"entity":"tax","action":"upsert","payload":[{"name":"A"}]},{"key":"second","entity":"no_such_entity","action":"upsert","payload":[{"name":"B"}]}]', false);

        static::assertSame(McpToolError::INVALID_ARGUMENTS, $result->error?->code);
        static::assertStringContainsString('Operation "second": entity "no_such_entity" not found', $result->error->message);
    }

    public function testDeniesEveryMissingPrivilegeBeforeWriting(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('beginTransaction');

        $context = $this->contextWithPermissions(['tax:create', 'product:update']);
        $operations = \sprintf(
            '[{"entity":"tax","action":"upsert","payload":[{"name":"New"},{"id":"%s","name":"Existing"}]},{"entity":"product","action":"upsert","payload":[{"id":"%s","stock":1}]},{"entity":"product_category","action":"delete","payload":[{"productId":"%s","categoryId":"%s"}]}]',
            self::PRODUCT_ID,
            self::PRODUCT_ID,
            self::PRODUCT_ID,
            self::CATEGORY_ID,
        );

        $result = $this->createTool($this->neverSyncs(), $connection, $context)($operations);

        static::assertSame(McpToolError::MISSING_PRIVILEGE, $result->error?->code);
        static::assertSame('Missing privilege: tax:update, product_category:delete', $result->error->message);
    }

    public function testAllowsWhatTheOperationsNeed(): void
    {
        $context = $this->contextWithPermissions(['tax:update', 'product_category:delete']);

        $syncService = $this->createMock(SyncServiceInterface::class);
        $syncService->expects($this->once())->method('sync')->willReturn(new SyncResult([]));

        $result = $this->createTool($syncService, context: $context)(
            \sprintf(
                '[{"entity":"tax","action":"upsert","payload":[{"id":"%s","name":"Existing"}]},{"entity":"product_category","action":"delete","payload":[{"productId":"%s","categoryId":"%s"}]}]',
                self::PRODUCT_ID,
                self::PRODUCT_ID,
                self::CATEGORY_ID,
            ),
            false,
        );

        static::assertNull($result->error);
    }

    public function testReportsPrivilegesTheDalDenies(): void
    {
        $syncService = static::createStub(SyncServiceInterface::class);
        $syncService->method('sync')->willThrowException(new MissingPrivilegeException(['category:create']));

        $result = $this->createTool($syncService)('[{"entity":"tax","action":"upsert","payload":[{"name":"A"}]}]', false);

        static::assertSame(McpToolError::MISSING_PRIVILEGE, $result->error?->code);
        static::assertSame('Missing privilege: category:create', $result->error->message);
    }

    public function testReportsWriteErrorsOfADryRun(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('rollBack');

        $syncService = static::createStub(SyncServiceInterface::class);
        $syncService->method('sync')->willThrowException(new WriteException());

        $result = $this->createTool($syncService, $connection)('[{"entity":"tax","action":"upsert","payload":[{"name":"A"}]}]');

        static::assertSame(McpToolError::INVALID_ARGUMENTS, $result->error?->code);
    }

    public function testReportsUnexpectedFailuresOfADryRun(): void
    {
        $syncService = static::createStub(SyncServiceInterface::class);
        $syncService->method('sync')->willThrowException(new \RuntimeException('Deadlock'));

        $result = $this->createTool($syncService)('[{"entity":"tax","action":"upsert","payload":[{"name":"A"}]}]');

        static::assertEquals(McpToolResult::failure('Deadlock'), $result);
    }

    public function testRejectsMappingDeletesWithoutEveryKeyField(): void
    {
        $result = $this->createTool($this->neverSyncs(), definition: new ProductCategoryDefinition())(
            \sprintf('[{"key":"unlink","entity":"product_category","action":"delete","payload":[{"productId":"%s"}]}]', self::PRODUCT_ID),
            false,
        );

        static::assertSame(McpToolError::INVALID_ARGUMENTS, $result->error?->code);
        static::assertSame(
            'Operation "unlink": entity "product_category" has a composite primary key. Name productId and categoryId in every payload row, e.g. [{"productId":"...","categoryId":"..."}].',
            $result->error->message,
        );
    }

    public function testRejectsMappingDeletesWithoutVersionFieldsOutsideTheLiveVersion(): void
    {
        $draft = Uuid::randomHex();
        $context = Context::createDefaultContext()->createWithVersionId($draft);

        $result = $this->createTool($this->neverSyncs(), context: $context, definition: new ProductCategoryDefinition())(
            \sprintf('[{"key":"unlink","entity":"product_category","action":"delete","payload":[{"productId":"%s","categoryId":"%s"}]}]', self::PRODUCT_ID, self::CATEGORY_ID),
            false,
        );

        static::assertSame(McpToolError::INVALID_ARGUMENTS, $result->error?->code);
        static::assertStringContainsString(\sprintf('Operation "unlink": this request runs in version %s.', $draft), $result->error->message);
    }

    public function testAcceptsCriteriaDeletesOnMappingEntities(): void
    {
        $syncService = $this->createMock(SyncServiceInterface::class);
        $syncService->expects($this->once())->method('sync')->willReturn(new SyncResult([]));

        $result = $this->createTool($syncService, definition: new ProductCategoryDefinition())(
            \sprintf('[{"entity":"product_category","action":"delete","criteria":[{"type":"equals","field":"productId","value":"%s"}]}]', self::PRODUCT_ID),
            false,
        );

        static::assertNull($result->error);
    }

    private function createTool(
        SyncServiceInterface $syncService,
        ?Connection $connection = null,
        ?Context $context = null,
        ?EntityDefinition $definition = null,
    ): EntitySyncTool {
        $definition ??= new ProductDefinition();
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getByEntityName')->willReturn($definition);

        return new EntitySyncTool(
            $syncService,
            $registry,
            $this->contextProvider($context ?? Context::createDefaultContext()),
            $connection ?? static::createStub(Connection::class),
        );
    }

    private function contextProvider(Context $context): McpContextProvider
    {
        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        return $contextProvider;
    }

    /**
     * @param list<string> $permissions
     */
    private function contextWithPermissions(array $permissions): Context
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions($permissions);

        return new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);
    }

    private function neverSyncs(): SyncServiceInterface
    {
        $syncService = $this->createMock(SyncServiceInterface::class);
        $syncService->expects($this->never())->method('sync');

        return $syncService;
    }
}
