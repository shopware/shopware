<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefundPosition\OrderTransactionCaptureRefundPositionDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_5\Migration1689856589AddVersioningForOrderTransactionCaptures;

/**
 * @package checkout
 *
 * @internal
 */
#[CoversClass(Migration1689856589AddVersioningForOrderTransactionCaptures::class)]
class Migration1689856589AddVersioningForOrderTransactionCapturesTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testMigrationCanExecuteMultipleTimes(): void
    {
        $migration = new Migration1689856589AddVersioningForOrderTransactionCaptures();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $captureColumns = $this->getColumns(OrderTransactionCaptureDefinition::ENTITY_NAME);
        static::assertArrayHasKey('version_id', $captureColumns);

        $captureRefundColumns = $this->getColumns(OrderTransactionCaptureRefundDefinition::ENTITY_NAME);
        static::assertArrayHasKey('version_id', $captureRefundColumns);
        static::assertArrayHasKey('capture_version_id', $captureRefundColumns);

        $captureRefundPositionColumns = $this->getColumns(OrderTransactionCaptureRefundPositionDefinition::ENTITY_NAME);
        static::assertArrayHasKey('version_id', $captureRefundPositionColumns);
        static::assertArrayHasKey('refund_version_id', $captureRefundPositionColumns);
    }

    /**
     * @return array<string, Column>
     */
    private function getColumns(string $tableName): array
    {
        $schemaManager = $this->connection->createSchemaManager();

        return $schemaManager->listTableColumns($tableName);
    }
}
