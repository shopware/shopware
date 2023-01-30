<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1659256999CreateFlowTemplateTable;
use Shopware\Core\Migration\V6_4\Migration1659257296GenerateFlowTemplateDataFromEventAction;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1659257296GenerateFlowTemplateDataFromEventAction
 */
class Migration1659257296GenerateFlowTemplateDataFromEventActionTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1659257296GenerateFlowTemplateDataFromEventAction();
        static::assertEquals('1659257296', $migration->getCreationTimestamp());
    }

    public function testGenerateDefaultFlowTemplates(): void
    {
        $migration = new Migration1659256999CreateFlowTemplateTable();
        $migration->update($this->connection);

        $this->connection->executeStatement('DELETE FROM `flow_template`');

        $migration = new Migration1659257296GenerateFlowTemplateDataFromEventAction();

        // should work as expected if executed multiple times
        $migration->update($this->connection);
        $migration->update($this->connection);

        $countFlowSequences = $this->connection->fetchOne('SELECT COUNT(*) FROM `flow_template`');
        static::assertEquals(26, $countFlowSequences);
    }
}
