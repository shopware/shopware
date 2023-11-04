<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1649040981CorrectStateMachineStateTranslationName;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1649040981CorrectStateMachineStateTranslationName
 */
class Migration1649040981CorrectStateMachineStateTranslationNameTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testHaveBeenChangeStateMachineStateChange(): void
    {
        $actualName = 'In progress';
        $expectName = 'In Progress';

        $stateMachineStateId = $this->connection->fetchOne('SELECT id FROM state_machine_state');
        $this->connection->executeStatement('DELETE FROM state_machine_state_translation WHERE state_machine_state_id = :id OR name = :name', [
            'id' => $stateMachineStateId,
            'name' => $actualName,
        ]);
        $this->connection->insert('state_machine_state_translation', [
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'state_machine_state_id' => $stateMachineStateId,
            'name' => $actualName,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $stateName = $this->connection->fetchOne('SELECT name FROM state_machine_state_translation WHERE name = :name', [
            'name' => $actualName,
        ]);

        static::assertEquals($actualName, $stateName);
        static::assertNotEquals($expectName, $stateName);

        (new Migration1649040981CorrectStateMachineStateTranslationName())->update($this->connection);

        $stateName = $this->connection->fetchOne('SELECT name FROM state_machine_state_translation WHERE name = :name', [
            'name' => $actualName,
        ]);

        static::assertEquals($expectName, $stateName);
        static::assertNotEquals($actualName, $stateName);
    }
}
