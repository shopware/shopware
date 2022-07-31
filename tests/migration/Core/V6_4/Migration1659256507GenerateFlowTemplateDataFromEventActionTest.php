<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1659256507GenerateFlowTemplateDataFromEventAction;

/**
 * @internal
 * @covers \Shopware\Core\Migration\V6_4\Migration1659256507GenerateFlowTemplateDataFromEventAction
 */
class Migration1659256507GenerateFlowTemplateDataFromEventActionTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DELETE FROM `flow`');
        $this->connection->executeStatement('DELETE FROM `flow_sequence`');
    }

    public function testGenerateDefaultFlowTemplates(): void
    {
        $migration = new Migration1659256507GenerateFlowTemplateDataFromEventAction();
        $migration->update($this->connection);

        $countFlows = $this->connection->fetchOne('SELECT COUNT(*) FROM `flow` where locked = 1');
        static::assertEquals(26, $countFlows);

        $countFlowSequences = $this->connection->fetchOne('SELECT COUNT(*) FROM `flow_sequence`');
        static::assertEquals(26, $countFlowSequences);
    }
}
