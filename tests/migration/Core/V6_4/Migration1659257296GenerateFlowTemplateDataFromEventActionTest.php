<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1659256999AddLockedFieldToFlowTable;
use Shopware\Core\Migration\V6_4\Migration1659257296GenerateFlowTemplateDataFromEventAction;

/**
 * @internal
 * @covers \Shopware\Core\Migration\V6_4\Migration1659257296GenerateFlowTemplateDataFromEventAction
 */
class Migration1659257296GenerateFlowTemplateDataFromEventActionTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DELETE FROM `flow_sequence`');
        $this->connection->executeStatement('DELETE FROM `flow`');
    }

    public function testGenerateDefaultFlowTemplates(): void
    {
        $migration = new Migration1659256999AddLockedFieldToFlowTable();
        $migration->update($this->connection);

        $migration = new Migration1659257296GenerateFlowTemplateDataFromEventAction();
        $migration->update($this->connection);

        $countFlows = $this->connection->fetchOne('SELECT COUNT(*) FROM `flow` where locked = 1');
        static::assertEquals(26, $countFlows);

        $countFlowSequences = $this->connection->fetchOne('SELECT COUNT(*) FROM `flow_sequence`');
        static::assertEquals(26, $countFlowSequences);
    }
}
