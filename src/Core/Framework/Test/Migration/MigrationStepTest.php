<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Test\Migration\_test_trigger_with_trigger_\MigrationWithBackwardTrigger;
use Shopware\Core\Framework\Test\Migration\_test_trigger_with_trigger_\MigrationWithForwardTrigger;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MigrationStepTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function tearDown(): void
    {
        $this->removeMigrationFromTable(new MigrationWithForwardTrigger());
        $this->removeMigrationFromTable(new MigrationWithBackwardTrigger());
    }

    public function test_update_AddATrigger(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $migration = new MigrationWithForwardTrigger();
        $migration->update($connection);

        $this->assertTriggerExists(MigrationWithForwardTrigger::TRIGGER_NAME);
        $this->removeTrigger(MigrationWithForwardTrigger::TRIGGER_NAME);
    }

    public function test_update_ForwardTrigger_isExecutedIfMigrationIsNotActive(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeUpdate('SET @MIGRATION_1_IS_ACTIVE = NULL');

        $migration = new MigrationWithForwardTrigger();
        $migration->update($connection);

        $this->addMigrationToTable($migration);

        $inserted = $connection->executeQuery(
            'SELECT * FROM `migration` WHERE `class` = :class',
            [
                'class' => MigrationWithForwardTrigger::class,
            ]
        )->fetch();

        //the trigger adds 1 to creation_timestamp
        static::assertEquals($migration->getCreationTimestamp() + 1, $inserted['creation_timestamp']);

        $this->removeTrigger(MigrationWithForwardTrigger::TRIGGER_NAME);
    }

    public function test_update_ForwardTriggerIsSkipped_IfMigrationIsActive(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeUpdate('SET @MIGRATION_1_IS_ACTIVE = TRUE');

        $migration = new MigrationWithForwardTrigger();
        $migration->update($connection);

        $this->addMigrationToTable($migration);

        $inserted = $connection->executeQuery(
            'SELECT * FROM `migration` WHERE `class` = :class',
            [
                'class' => MigrationWithForwardTrigger::class,
            ]
        )->fetch();

        //the trigger should not add 1 to creation_timestamp
        static::assertEquals($migration->getCreationTimestamp(), $inserted['creation_timestamp']);

        $this->removeTrigger(MigrationWithForwardTrigger::TRIGGER_NAME);
    }

    public function test_update_BackwardTrigger_IsSkippedIfMigrationIsNotActive(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeUpdate('SET @MIGRATION_2_IS_ACTIVE = NULL');

        $migration = new MigrationWithBackwardTrigger();
        $migration->update($connection);

        $this->addMigrationToTable($migration);

        $inserted = $connection->executeQuery(
            'SELECT * FROM `migration` WHERE `class` = :class',
            [
                'class' => MigrationWithBackwardTrigger::class,
            ]
        )->fetch();

        //the trigger should not add 1 to creation_timestamp
        static::assertEquals($migration->getCreationTimestamp(), $inserted['creation_timestamp']);

        $this->removeTrigger(MigrationWithBackwardTrigger::TRIGGER_NAME);
    }

    public function test_update_BackwardTrigger_isExecutedIfMigrationIsActive(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeUpdate('SET @MIGRATION_2_IS_ACTIVE = TRUE');

        $migration = new MigrationWithBackwardTrigger();
        $migration->update($connection);

        $this->addMigrationToTable($migration);

        $inserted = $connection->executeQuery(
            'SELECT * FROM `migration` WHERE `class` = :class',
            [
                'class' => MigrationWithBackwardTrigger::class,
            ]
        )->fetch();

        //the trigger adds 1 to creation_timestamp
        static::assertEquals($migration->getCreationTimestamp() + 1, $inserted['creation_timestamp']);

        $this->removeTrigger(MigrationWithBackwardTrigger::TRIGGER_NAME);
    }

    private function addMigrationToTable(MigrationStep $migration): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $this->removeMigrationFromTable($migration);
        $connection->executeUpdate(
            sprintf(
                'INSERT INTO `migration` VALUES(\'%s\', %d, NOW(), NOW(), null);',
                str_replace('\\', '\\\\', get_class($migration)),
                $migration->getCreationTimestamp()
            )
        );
    }

    private function removeMigrationFromTable(MigrationStep $migration): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeUpdate(
            'DELETE FROM `migration` WHERE `class` = :class',
            ['class' => get_class($migration)]
        );
    }

    private function removeTrigger($name): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeUpdate(sprintf('DROP TRIGGER %s;', $name));
    }

    private function assertTriggerExists($name): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $trigger = $connection->executeQuery(sprintf('SHOW TRIGGERS WHERE `Trigger` =  \'%s\'', $name))->fetch(\PDO::FETCH_COLUMN);
        static::assertEquals($name, $trigger);
    }
}
