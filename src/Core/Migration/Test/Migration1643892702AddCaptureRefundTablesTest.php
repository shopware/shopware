<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefundPosition\OrderTransactionCaptureRefundPositionDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1643892702AddCaptureRefundTables;

/**
 * @internal
 */
#[Package('core')]
class Migration1643892702AddCaptureRefundTablesTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testMigration(): void
    {
        $this->rollback();

        static::assertFalse($this->tableExists(OrderTransactionCaptureDefinition::ENTITY_NAME));
        static::assertFalse($this->tableExists(OrderTransactionCaptureRefundDefinition::ENTITY_NAME));
        static::assertFalse($this->tableExists(OrderTransactionCaptureRefundPositionDefinition::ENTITY_NAME));

        $this->execute();

        static::assertTrue($this->tableExists(OrderTransactionCaptureDefinition::ENTITY_NAME));
        static::assertTrue($this->tableExists(OrderTransactionCaptureRefundDefinition::ENTITY_NAME));
        static::assertTrue($this->tableExists(OrderTransactionCaptureRefundPositionDefinition::ENTITY_NAME));
    }

    public function testMigrationTwice(): void
    {
        $this->rollback();

        static::assertFalse($this->tableExists(OrderTransactionCaptureDefinition::ENTITY_NAME));
        static::assertFalse($this->tableExists(OrderTransactionCaptureRefundDefinition::ENTITY_NAME));
        static::assertFalse($this->tableExists(OrderTransactionCaptureRefundPositionDefinition::ENTITY_NAME));

        $this->execute();
        $this->execute();

        static::assertTrue($this->tableExists(OrderTransactionCaptureDefinition::ENTITY_NAME));
        static::assertTrue($this->tableExists(OrderTransactionCaptureRefundDefinition::ENTITY_NAME));
        static::assertTrue($this->tableExists(OrderTransactionCaptureRefundPositionDefinition::ENTITY_NAME));
    }

    private function rollback(): void
    {
        $sql = 'DROP TABLE `#table#`';

        $this->connection->executeStatement(\str_replace('#table#', OrderTransactionCaptureRefundPositionDefinition::ENTITY_NAME, $sql));
        $this->connection->executeStatement(\str_replace('#table#', OrderTransactionCaptureRefundDefinition::ENTITY_NAME, $sql));
        $this->connection->executeStatement(\str_replace('#table#', OrderTransactionCaptureDefinition::ENTITY_NAME, $sql));
    }

    private function execute(): void
    {
        (new Migration1643892702AddCaptureRefundTables())->update($this->connection);
    }

    private function tableExists(string $tableName): bool
    {
        $sql = \str_replace('#table#', $tableName, 'DESC `#table#`');

        try {
            $this->connection->executeQuery($sql);
        } catch (Exception) {
            return false;
        }

        return true;
    }
}
