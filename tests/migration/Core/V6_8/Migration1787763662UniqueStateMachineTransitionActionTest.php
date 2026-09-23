<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_8\Migration1787763662UniqueStateMachineTransitionAction;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1787763662UniqueStateMachineTransitionAction::class)]
class Migration1787763662UniqueStateMachineTransitionActionTest extends TestCase
{
    private const INDEX_NAME = 'uniq.state_machine_transition.action_name_from_state';
    private const PREVIOUS_INDEX_NAME = 'uniq.state_machine_transition.action_name_state_machine';
    private const FIXTURE_ACTION_NAME = 'test_duplicate_destination_action';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `state_machine_transition` WHERE `action_name` = :actionName',
            ['actionName' => self::FIXTURE_ACTION_NAME]
        );
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1787763662, (new Migration1787763662UniqueStateMachineTransitionAction())->getCreationTimestamp());
    }

    public function testUpdateRemovesDuplicateDestinationsAndTightensUniqueKey(): void
    {
        $this->restoreLegacyUniqueIndex();

        $stateMachineId = $this->connection->fetchOne(
            'SELECT `id` FROM `state_machine` WHERE `technical_name` = :technicalName',
            ['technicalName' => 'order_transaction.state']
        );
        static::assertIsString($stateMachineId);

        $stateIds = $this->connection->fetchFirstColumn(
            'SELECT `id` FROM `state_machine_state` WHERE `state_machine_id` = :stateMachineId ORDER BY `technical_name` LIMIT 3',
            ['stateMachineId' => $stateMachineId]
        );
        static::assertCount(3, $stateIds);
        [$fromStateId, $olderDestinationId, $newerDestinationId] = $stateIds;

        $olderTransitionId = $this->insertTransition($stateMachineId, $fromStateId, $olderDestinationId, '2020-01-01 00:00:00.000');
        $this->insertTransition($stateMachineId, $fromStateId, $newerDestinationId, '2024-01-01 00:00:00.000');

        $migration = new Migration1787763662UniqueStateMachineTransitionAction();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $remaining = $this->connection->fetchAllAssociative(
            'SELECT `id`, `to_state_id` FROM `state_machine_transition` WHERE `action_name` = :actionName',
            ['actionName' => self::FIXTURE_ACTION_NAME]
        );
        static::assertCount(1, $remaining);
        static::assertSame($olderTransitionId, $remaining[0]['id']);
        static::assertSame($olderDestinationId, $remaining[0]['to_state_id']);

        static::assertSame(['action_name', 'state_machine_id', 'from_state_id'], $this->getIndexColumns(self::INDEX_NAME));
        static::assertSame([], $this->getIndexColumns(self::PREVIOUS_INDEX_NAME));
    }

    private function restoreLegacyUniqueIndex(): void
    {
        if ($this->getIndexColumns(self::INDEX_NAME) !== []) {
            $this->connection->executeStatement(
                \sprintf('ALTER TABLE `state_machine_transition` DROP INDEX `%s`', self::INDEX_NAME)
            );
        }

        if ($this->getIndexColumns(self::PREVIOUS_INDEX_NAME) !== []) {
            return;
        }

        $this->connection->executeStatement(
            \sprintf(
                'ALTER TABLE `state_machine_transition` ADD UNIQUE `%s` (`action_name`, `state_machine_id`, `from_state_id`, `to_state_id`)',
                self::PREVIOUS_INDEX_NAME
            )
        );
    }

    private function insertTransition(string $stateMachineId, string $fromStateId, string $toStateId, string $createdAt): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('state_machine_transition', [
            'id' => $id,
            'action_name' => self::FIXTURE_ACTION_NAME,
            'state_machine_id' => $stateMachineId,
            'from_state_id' => $fromStateId,
            'to_state_id' => $toStateId,
            'created_at' => $createdAt,
        ]);

        return $id;
    }

    /**
     * @return list<string>
     */
    private function getIndexColumns(string $indexName): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT `COLUMN_NAME` FROM `information_schema`.`STATISTICS`
             WHERE `TABLE_SCHEMA` = DATABASE()
                 AND `TABLE_NAME` = :table
                 AND `INDEX_NAME` = :index
             ORDER BY `SEQ_IN_INDEX`',
            [
                'table' => 'state_machine_transition',
                'index' => $indexName,
            ]
        );
    }
}
