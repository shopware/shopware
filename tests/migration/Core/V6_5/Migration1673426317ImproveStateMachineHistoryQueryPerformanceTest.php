<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1673426317ImproveStateMachineHistoryQueryPerformance;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_5\Migration1673426317ImproveStateMachineHistoryQueryPerformance
 */
class Migration1673426317ImproveStateMachineHistoryQueryPerformanceTest extends TestCase
{
    private Connection $connection;

    private Migration1673426317ImproveStateMachineHistoryQueryPerformance $migration;

    private IdsCollection $ids;

    public function setUp(): void
    {
        $this->migration = new Migration1673426317ImproveStateMachineHistoryQueryPerformance();
        $this->connection = KernelLifecycleManager::getConnection();

        $this->ids = new IdsCollection([
            'id' => Uuid::randomHex(),
            'referenced_id' => Uuid::randomHex(),
            'referenced_version_id' => Uuid::randomHex(),
            'empty_id' => '00000000000000000000000000000000',
        ]);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertEquals('1673426317', $this->migration->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $this->migration->update($this->connection);

        static::assertStringContainsString('`referenced_id` binary(16)', $this->getSchema());
        static::assertStringContainsString('`referenced_version_id` binary(16)', $this->getSchema());
    }

    public function testUpdateTwice(): void
    {
        $this->migration->update($this->connection);

        static::assertStringContainsString('`referenced_id` binary(16)', $this->getSchema());
        static::assertStringContainsString('`referenced_version_id` binary(16)', $this->getSchema());

        $expected = $this->getSchema();

        $this->migration->update($this->connection);
        static::assertSame($expected, $this->getSchema());
    }

    /**
     * @deprecated tag:v6.6.0 - entityId will be removed. There is no need for this test anymore
     */
    public function testConversionFromJsonToBinary(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);

        $this->rollbackTable();

        $this->insertStateMachineHistoryEntry([
            'id' => $this->ids->get('referenced_id'),
            'version_id' => $this->ids->get('referenced_version_id'),
        ]);

        $this->migration->update($this->connection);

        $result = $this->connection->executeQuery('
            SELECT `referenced_id`, `referenced_version_id` FROM `state_machine_history` WHERE `id` = UNHEX(:id);
        ', ['id' => $this->ids->get('id')])->fetchAssociative();

        static::assertIsArray($result, 'Missing state machine history entry');

        static::assertEquals($this->ids->get('referenced_id'), Uuid::fromBytesToHex($result['referenced_id']));
        static::assertEquals($this->ids->get('referenced_version_id'), Uuid::fromBytesToHex($result['referenced_version_id']));
    }

    /**
     * @deprecated tag:v6.6.0 - entityId will be removed. There is no need for this test anymore
     */
    public function testConversionFromJsonToBinaryWithoutVersionId(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);

        $this->rollbackTable();

        $this->insertStateMachineHistoryEntry([
            'id' => $this->ids->get('referenced_id'),
        ]);

        $this->migration->update($this->connection);

        $result = $this->connection->executeQuery('
            SELECT `referenced_id`, `referenced_version_id` FROM `state_machine_history` WHERE `id` = UNHEX(:id);
        ', ['id' => $this->ids->get('id')])->fetchAssociative();

        static::assertIsArray($result, 'Missing state machine history entry');

        static::assertEquals($this->ids->get('referenced_id'), Uuid::fromBytesToHex($result['referenced_id']));
        static::assertEquals($this->ids->get('empty_id'), Uuid::fromBytesToHex($result['referenced_version_id']));

        $this->clearTable();
    }

    /**
     * @param array<string, string> $json
     */
    private function insertStateMachineHistoryEntry(array $json = []): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0;');

        $this->connection->executeStatement('
            INSERT INTO `state_machine_history`(`id`, `state_machine_id`, `entity_name`, `entity_id`, `from_state_id`, `to_state_id`, `action_name`, `created_at`)
            VALUES (:id, :randomId, \'entityName\', :entityId, :randomId, :randomId, \'actionState\', \'2023-01-11 00:00:00\');
        ', [
            'id' => Uuid::fromHexToBytes($this->ids->get('id')),
            'randomId' => Uuid::randomBytes(),
            'entityId' => \json_encode($json),
        ]);

        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function rollbackTable(): void
    {
        $this->connection->executeStatement('
            ALTER TABLE `state_machine_history`
                DROP `referenced_id`,
                DROP `referenced_version_id`;
        ');

        static::assertStringNotContainsString('referenced_id', $this->getSchema());
        static::assertStringNotContainsString('referenced_version_id', $this->getSchema());
    }

    private function clearTable(): void
    {
        $this->connection->executeStatement('
            SET FOREIGN_KEY_CHECKS=0;
            TRUNCATE TABLE `state_machine_history`;
            SET FOREIGN_KEY_CHECKS=1;
        ');
    }

    /**
     * @throws \Throwable
     */
    private function getSchema(): string
    {
        $schema = $this->connection->fetchAssociative(sprintf('SHOW CREATE TABLE `%s`', 'state_machine_history'));
        static::assertNotFalse($schema);
        static::assertIsString($schema['Create Table']);

        return $schema['Create Table'];
    }
}
