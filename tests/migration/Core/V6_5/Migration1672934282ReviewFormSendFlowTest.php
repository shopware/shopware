<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1672934282ReviewFormSendFlow;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1672934282ReviewFormSendFlow::class)]
class Migration1672934282ReviewFormSendFlowTest extends TestCase
{
    use MigrationTestTrait;

    private const FLOW_NAME = 'Review form sent';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->prepare();
    }

    public function testMigration(): void
    {
        $migration = new Migration1672934282ReviewFormSendFlow();
        $migration->update($this->connection);

        $this->assertFlow();

        // Test multiple execution
        $migration->update($this->connection);
    }

    private function assertFlow(): void
    {
        $flowId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `flow` WHERE `name` = :name',
            ['name' => self::FLOW_NAME]
        );

        static::assertIsString($flowId);

        $flowSequenceAction = $this->connection->fetchOne(
            'SELECT `action_name` FROM `flow_sequence` WHERE `flow_id` = :id',
            ['id' => Uuid::fromHexToBytes($flowId)]
        );

        static::assertEquals('action.mail.send', $flowSequenceAction);
    }

    private function prepare(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `flow` WHERE `name` = :name',
            ['name' => self::FLOW_NAME]
        );
    }
}
