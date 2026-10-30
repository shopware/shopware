<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Write\Validation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\RowLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\OutcomeRow;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class WriteOutcomeTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    /**
     * @var list<OutcomeRow>
     */
    private array $touchedRows = [];

    /**
     * @var list<OutcomeRow>
     */
    private array $resultingRows = [];

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testAPartialCustomFieldsUpdateResultsInAllCustomFields(): void
    {
        $ids = new IdsCollection();
        $productRepository = static::getContainer()->get('product.repository');
        $productRepository->create([
            (new ProductBuilder($ids, 'p1'))->price(10)->customField('colour', 'red')->build(),
        ], Context::createDefaultContext());

        $writeOutcome = new WriteOutcome(new RowLoader(static::getContainer()->get(Connection::class)));
        $customFields = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            PreWriteValidationEvent::class,
            static function (PreWriteValidationEvent $event) use ($writeOutcome, &$customFields): void {
                foreach ($writeOutcome->resultingRows($event->getCommandsForEntity('product_translation'), ['custom_fields']) as $row) {
                    $customFields = $row->getResultingValue('custom_fields');
                }
            }
        );

        $productRepository->update([['id' => $ids->get('p1'), 'customFields' => ['size' => 'L']]], Context::createDefaultContext());

        static::assertIsString($customFields);
        static::assertSame(['colour' => 'red', 'size' => 'L'], Json::decodeToArray($customFields));
    }

    public function testTouchedRowsHoldEveryRecordTheWriteCreatesUpdatesOrDeletesWhateverItSets(): void
    {
        $this->createTax('updated');
        $this->createTax('deleted');
        $this->captureTaxRows();

        $this->sync([
            new SyncOperation('update', 'tax', SyncOperation::ACTION_UPSERT, [['id' => $this->ids->get('updated'), 'position' => 5]]),
            new SyncOperation('delete', 'tax', SyncOperation::ACTION_DELETE, [['id' => $this->ids->get('deleted')]]),
            new SyncOperation('create', 'tax', SyncOperation::ACTION_UPSERT, [$this->tax('created')]),
        ]);

        static::assertCount(3, $this->touchedRows);
        static::assertSame([false, false, 'updated', 'updated'], [$this->touchedRows[0]->isNew, $this->touchedRows[0]->isDeleted, $this->touchedRows[0]->getStoredValue('name'), $this->touchedRows[0]->getResultingValue('name')]);
        static::assertTrue($this->touchedRows[0]->payloadContainsOnly('position', 'updated_at'));
        static::assertSame([false, true, 'deleted', null], [$this->touchedRows[1]->isNew, $this->touchedRows[1]->isDeleted, $this->touchedRows[1]->getStoredValue('name'), $this->touchedRows[1]->getResultingValue('name')]);
        static::assertSame([true, false, null, 'created'], [$this->touchedRows[2]->isNew, $this->touchedRows[2]->isDeleted, $this->touchedRows[2]->getStoredValue('name'), $this->touchedRows[2]->getResultingValue('name')]);
    }

    public function testATouchedRowHoldsTheValueBeforeAndAfterTheWrite(): void
    {
        $this->createTax('tax');
        $this->captureTaxRows();

        static::getContainer()->get('tax.repository')->update([['id' => $this->ids->get('tax'), 'name' => 'renamed']], Context::createDefaultContext());

        static::assertCount(1, $this->touchedRows);
        static::assertSame(['tax', 'renamed'], [$this->touchedRows[0]->getStoredValue('name'), $this->touchedRows[0]->getResultingValue('name')]);
    }

    public function testARecordDeletedAndCreatedAgainInOneRequestIsNewAndDeletedWithItsStoredRow(): void
    {
        $this->createTax('tax');
        $this->captureTaxRows();

        $this->sync([
            new SyncOperation('delete', 'tax', SyncOperation::ACTION_DELETE, [['id' => $this->ids->get('tax')]]),
            new SyncOperation('create', 'tax', SyncOperation::ACTION_UPSERT, [['id' => $this->ids->get('tax'), 'name' => 'replacement', 'taxRate' => 19.0, 'position' => 1]]),
        ]);

        static::assertCount(1, $this->touchedRows);
        static::assertSame(
            [true, true, 'tax', 'replacement'],
            [$this->touchedRows[0]->isNew, $this->touchedRows[0]->isDeleted, $this->touchedRows[0]->getStoredValue('name'), $this->touchedRows[0]->getResultingValue('name')]
        );
        static::assertCount(1, $this->resultingRows);
        static::assertSame([true, 'replacement'], [$this->resultingRows[0]->isNew, $this->resultingRows[0]->getResultingValue('name')]);
    }

    private function captureTaxRows(): void
    {
        $writeOutcome = new WriteOutcome(new RowLoader(static::getContainer()->get(Connection::class)));
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            PreWriteValidationEvent::class,
            function (PreWriteValidationEvent $event) use ($writeOutcome): void {
                foreach ($writeOutcome->touchedRows($event->getCommandsForEntity('tax'), ['name']) as $row) {
                    $this->touchedRows[] = $row;
                }

                foreach ($writeOutcome->resultingRows($event->getCommandsForEntity('tax'), ['name']) as $row) {
                    $this->resultingRows[] = $row;
                }
            }
        );
    }

    private function createTax(string $name): void
    {
        static::getContainer()->get('tax.repository')->create([$this->tax($name)], Context::createDefaultContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function tax(string $name): array
    {
        return ['id' => $this->ids->get($name), 'name' => $name, 'taxRate' => 19.0, 'position' => 1];
    }

    /**
     * @param list<SyncOperation> $operations
     */
    private function sync(array $operations): void
    {
        static::getContainer()->get(SyncService::class)->sync($operations, Context::createDefaultContext(), new SyncBehavior());
    }
}
