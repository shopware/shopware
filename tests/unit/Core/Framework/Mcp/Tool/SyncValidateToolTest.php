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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\SyncValidateTool;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SyncValidateTool::class)]
class SyncValidateToolTest extends TestCase
{
    private DefinitionInstanceRegistry&MockObject $registry;

    private McpContextProvider&MockObject $contextProvider;

    private Connection&MockObject $connection;

    private SyncValidateTool $tool;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->contextProvider = $this->createMock(McpContextProvider::class);
        $this->connection = $this->createMock(Connection::class);

        $source = new AdminApiSource(null, null);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);
        $this->contextProvider->method('getContext')->willReturn($context);

        $this->tool = new SyncValidateTool($this->registry, $this->contextProvider, $this->connection);
    }

    public function testReturnTypeIsAlwaysString(): void
    {
        $result = ($this->tool)('[]');
        static::assertIsString($result);

        $decoded = json_decode($result, true);
        static::assertIsArray($decoded);
        static::assertArrayHasKey('success', $decoded);
    }

    public function testInvalidJsonReturnsError(): void
    {
        $result = json_decode(($this->tool)('{not valid json}'), true);

        static::assertFalse($result['success']);
        static::assertStringContainsString('Invalid JSON', $result['error']);
    }

    public function testNonArrayOperationsReturnsError(): void
    {
        $result = json_decode(($this->tool)('"a string not an array"'), true);

        static::assertFalse($result['success']);
    }

    public function testEmptyArraySucceedsWithZeroErrors(): void
    {
        $result = json_decode(($this->tool)('[]'), true);

        static::assertTrue($result['success']);
        static::assertEquals(0, $result['data']['total_invalid_records']);
        static::assertEquals(0, $result['data']['operations_validated']);
    }

    public function testUnknownEntityIsReportedPerOperation(): void
    {
        $this->registry
            ->method('getRepository')
            ->willThrowException(new \InvalidArgumentException('Entity not found'));

        $ops = [['action' => 'upsert', 'entity' => 'nonexistent_entity_xyz', 'payload' => [['name' => 'test']]]];
        $result = json_decode(($this->tool)(json_encode($ops)), true);

        static::assertTrue($result['success']);
        static::assertGreaterThan(0, $result['data']['total_invalid_records']);
        static::assertStringContainsString('nonexistent_entity_xyz', $result['data']['operations'][0]['error']);
    }

    public function testMissingEntityFieldReturnsOperationError(): void
    {
        $ops = [['action' => 'upsert', 'payload' => [['name' => 'test']]]];
        $result = json_decode(($this->tool)(json_encode($ops)), true);

        static::assertTrue($result['success']);
        static::assertGreaterThan(0, $result['data']['total_invalid_records']);
        static::assertStringContainsString('Missing required fields', $result['data']['operations'][0]['error']);
    }

    public function testValidationModeIsAlwaysDryRun(): void
    {
        $result = json_decode(($this->tool)('[]'), true);

        static::assertEquals('dry_run_no_write', $result['data']['validation_mode']);
    }

    public function testNoteIndicatesAllValidWhenNoErrors(): void
    {
        $result = json_decode(($this->tool)('[]'), true);

        static::assertStringContainsString('valid', $result['data']['note']);
    }

    public function testNonObjectRecordReturnsRecordLevelError(): void
    {
        $repository = $this->createMock(\Shopware\Core\Framework\DataAbstractionLayer\EntityRepository::class);
        $this->registry->method('getRepository')->willReturn($repository);

        $ops = [['action' => 'upsert', 'entity' => 'product', 'payload' => ['not an object but a string']]];
        $result = json_decode(($this->tool)(json_encode($ops)), true);

        static::assertTrue($result['success']);
        $records = $result['data']['operations'][0]['records'];
        static::assertCount(1, $records);
        static::assertFalse($records[0]['valid']);
    }

    public function testSuccessfulDryRunReturnsZeroInvalidRecords(): void
    {
        $repository = $this->createMock(\Shopware\Core\Framework\DataAbstractionLayer\EntityRepository::class);
        $this->registry->method('getRepository')->willReturn($repository);

        // Connection mock: beginTransaction, rollBack (executeWithDryRun pattern)
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');

        $ops = [['action' => 'upsert', 'entity' => 'product', 'payload' => [['id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaabbb', 'name' => 'Test']]]];
        $result = json_decode(($this->tool)(json_encode($ops)), true);

        static::assertTrue($result['success']);
        static::assertEquals(0, $result['data']['total_invalid_records']);
        static::assertTrue($result['data']['operations'][0]['records'][0]['valid']);
    }
}
