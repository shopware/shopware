<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Write\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\RowLoader;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WriteOutcome::class)]
class WriteOutcomeTest extends TestCase
{
    private EntityDefinition $productDefinition;

    /**
     * @var array<string, string>
     */
    private array $productPrimaryKey;

    protected function setUp(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [ProductDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $this->productDefinition = $registry->getByEntityName('product');
        $this->productPrimaryKey = ['id' => Uuid::randomBytes(), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)];
    }

    public function testTheColumnsToReadMustBeNamed(): void
    {
        $this->expectExceptionObject(DataAbstractionLayerException::writeOutcomeWithoutColumns());

        // @phpstan-ignore argument.type (an empty list must be rejected at runtime)
        (new WriteOutcome(static::createStub(RowLoader::class)))->resultingRows([$this->updateProduct(['stock' => 2])], []);
    }

    public function testTheWritesOfARecordAreMergedInOrder(): void
    {
        $rows = (new WriteOutcome(static::createStub(RowLoader::class)))->resultingRows([
            $this->insertProduct(['product_number' => 'p1', 'stock' => 1]),
            $this->updateProduct(['stock' => 2]),
        ], ['product_number', 'stock']);

        static::assertCount(1, $rows);
        static::assertTrue($rows[0]->isNew);
        static::assertSame(['p1', 2], [$rows[0]->getResultingValue('product_number'), $rows[0]->getResultingValue('stock')]);
    }

    public function testThePathIsTheLastWriteThatSetsARequestedColumn(): void
    {
        $rows = (new WriteOutcome(static::createStub(RowLoader::class)))->resultingRows([
            $this->insertProduct(['product_number' => 'p1', 'stock' => 1], '/0/0'),
            $this->updateProduct(['stock' => 2], '/1/0'),
            $this->updateProduct(['product_number' => 'p2'], '/2/0'),
        ], ['stock']);

        static::assertSame('/1/0', $rows[0]->path);
    }

    public function testAResultingRowKnowsEveryColumnTheWriteSets(): void
    {
        $row = (new WriteOutcome(static::createStub(RowLoader::class)))->resultingRows([
            $this->updateProduct(['stock' => 2]),
            $this->updateProduct(['product_number' => 'renamed']),
        ], ['stock'])[0];

        static::assertFalse($row->payloadContainsOnly('stock'));
    }

    public function testALaterUpdateDoesNotHideAnEarlierOne(): void
    {
        $rowLoader = static::createStub(RowLoader::class);
        $rowLoader->method('load')->willReturn([[...$this->productPrimaryKey, 'stock' => '1']]);

        $rows = (new WriteOutcome($rowLoader))->resultingRows([
            $this->updateProduct(['stock' => 2], '/0/0'),
            $this->updateProduct(['product_number' => 'renamed'], '/1/0'),
        ], ['stock']);

        static::assertCount(1, $rows);
        static::assertSame('/0/0', $rows[0]->path);
        static::assertSame(2, $rows[0]->getResultingValue('stock'));
    }

    public function testAColumnThePayloadDoesNotSetComesFromTheDatabase(): void
    {
        $rowLoader = $this->createMock(RowLoader::class);
        $rowLoader->expects($this->once())
            ->method('load')
            ->with('product', [$this->productPrimaryKey], ['id', 'version_id', 'stock', 'active'])
            ->willReturn([[...$this->productPrimaryKey, 'stock' => '1', 'active' => '1']]);

        $row = (new WriteOutcome($rowLoader))->resultingRows([$this->updateProduct(['stock' => 2])], ['stock', 'active'])[0];

        static::assertFalse($row->isNew);
        static::assertSame([2, '1'], [$row->getResultingValue('stock'), $row->getResultingValue('active')]);
    }

    public function testTheRowsOfAllRecordsAreLoadedInOneQuery(): void
    {
        $otherPrimaryKey = ['id' => Uuid::randomBytes(), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)];

        $rowLoader = $this->createMock(RowLoader::class);
        $rowLoader->expects($this->once())
            ->method('load')
            ->willReturn([
                [...$otherPrimaryKey, 'active' => '0'],
                [...$this->productPrimaryKey, 'active' => '1'],
            ]);

        $rows = (new WriteOutcome($rowLoader))->resultingRows([
            $this->updateProduct(['stock' => 2]),
            new UpdateCommand($this->productDefinition, ['stock' => 3], $otherPrimaryKey, static::createStub(EntityExistence::class), ''),
        ], ['stock', 'active']);

        static::assertSame(['1', '0'], [$rows[0]->getResultingValue('active'), $rows[1]->getResultingValue('active')]);
    }

    public function testOnlyTheRequestedColumnsOfTheStoredRowCanBeRead(): void
    {
        $rowLoader = static::createStub(RowLoader::class);
        $rowLoader->method('load')->willReturn([[...$this->productPrimaryKey, 'stock' => '1']]);

        $row = (new WriteOutcome($rowLoader))->resultingRows([$this->updateProduct(['stock' => 2])], ['stock'])[0];

        $this->expectExceptionObject(DataAbstractionLayerException::outcomeRowColumnNotRequested('id'));

        $row->getResultingValue('id');
    }

    public function testANewRecordHasNoValuesFromTheDatabase(): void
    {
        $rowLoader = $this->createMock(RowLoader::class);
        $rowLoader->expects($this->never())->method('load');

        $row = (new WriteOutcome($rowLoader))->resultingRows([$this->insertProduct(['stock' => 1])], ['active'])[0];

        static::assertNull($row->getResultingValue('active'));
    }

    public function testAnUpdateThatSetsNoneOfTheColumnsIsLeftOut(): void
    {
        $rowLoader = $this->createMock(RowLoader::class);
        $rowLoader->expects($this->never())->method('load');

        static::assertSame([], (new WriteOutcome($rowLoader))->resultingRows([$this->updateProduct(['product_number' => 'renamed'])], ['stock']));
    }

    public function testARecordWhoseLastWriteIsADeleteIsLeftOut(): void
    {
        $rows = (new WriteOutcome(static::createStub(RowLoader::class)))->resultingRows([
            $this->updateProduct(['stock' => 2]),
            new DeleteCommand($this->productDefinition, $this->productPrimaryKey, static::createStub(EntityExistence::class)),
        ], ['stock']);

        static::assertSame([], $rows);
    }

    public function testNoRowIsLoadedForARecordTheWriteDeletes(): void
    {
        $rowLoader = $this->createMock(RowLoader::class);
        $rowLoader->expects($this->never())->method('load');

        (new WriteOutcome($rowLoader))->resultingRows([
            $this->updateProduct(['stock' => 2]),
            new DeleteCommand($this->productDefinition, $this->productPrimaryKey, static::createStub(EntityExistence::class)),
        ], ['stock']);
    }

    public function testNoRowIsLoadedForARecordTheWriteDeletesAndCreatesAgain(): void
    {
        $rowLoader = $this->createMock(RowLoader::class);
        $rowLoader->expects($this->never())->method('load');

        (new WriteOutcome($rowLoader))->resultingRows([
            new DeleteCommand($this->productDefinition, $this->productPrimaryKey, static::createStub(EntityExistence::class)),
            $this->insertProduct(['stock' => 1]),
            $this->updateProduct(['stock' => 2]),
        ], ['stock']);
    }

    public function testAPartialCustomFieldsUpdateIsMergedIntoTheStoredValue(): void
    {
        $rowLoader = static::createStub(RowLoader::class);
        $rowLoader->method('load')->willReturn([[...$this->productPrimaryKey, 'custom_fields' => '{"colour": "red"}']]);

        $row = (new WriteOutcome($rowLoader))->resultingRows([$this->updateCustomFields(['size' => 'L'])], ['custom_fields'])[0];

        $customFields = $row->getResultingValue('custom_fields');
        static::assertTrue($row->payloadContainsAny('custom_fields'));
        static::assertIsString($customFields);
        static::assertSame(['colour' => 'red', 'size' => 'L'], Json::decodeToArray($customFields));
    }

    public function testAPartialCustomFieldsUpdateIsMergedIntoAnEarlierWrite(): void
    {
        $row = (new WriteOutcome(static::createStub(RowLoader::class)))->resultingRows([
            $this->insertProduct(['custom_fields' => '{"colour":"red"}']),
            $this->updateCustomFields(['size' => 'L']),
        ], ['custom_fields'])[0];

        $customFields = $row->getResultingValue('custom_fields');
        static::assertIsString($customFields);
        static::assertSame(['colour' => 'red', 'size' => 'L'], Json::decodeToArray($customFields));
    }

    public function testARecordInsertedAfterADeleteIsNew(): void
    {
        $row = (new WriteOutcome(static::createStub(RowLoader::class)))->resultingRows([
            new DeleteCommand($this->productDefinition, $this->productPrimaryKey, static::createStub(EntityExistence::class)),
            $this->insertProduct(['stock' => 1]),
        ], ['active'])[0];

        static::assertTrue($row->isNew);
        static::assertNull($row->getResultingValue('active'));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertProduct(array $payload, string $path = ''): InsertCommand
    {
        return new InsertCommand($this->productDefinition, $payload, $this->productPrimaryKey, static::createStub(EntityExistence::class), $path);
    }

    /**
     * @param array<string, mixed> $patch
     */
    private function updateCustomFields(array $patch): JsonUpdateCommand
    {
        return new JsonUpdateCommand($this->productDefinition, 'custom_fields', $patch, $this->productPrimaryKey, static::createStub(EntityExistence::class), '');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function updateProduct(array $payload, string $path = ''): UpdateCommand
    {
        return new UpdateCommand($this->productDefinition, $payload, $this->productPrimaryKey, static::createStub(EntityExistence::class), $path);
    }
}
