<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1780000100WidenWebhookDeliveryWebhookStatusIndex;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1780000100WidenWebhookDeliveryWebhookStatusIndex::class)]
class Migration1780000100WidenWebhookDeliveryWebhookStatusIndexTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testWidensIndexToIncludeId(): void
    {
        (new Migration1780000100WidenWebhookDeliveryWebhookStatusIndex())->update($this->connection);

        // Widened so the probe's oldest-held-row lookup (ORDER BY id) is index-covered.
        static::assertSame(['webhook_id', 'delivery_status', 'id'], $this->indexColumns());
    }

    public function testIsIdempotent(): void
    {
        $migration = new Migration1780000100WidenWebhookDeliveryWebhookStatusIndex();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(['webhook_id', 'delivery_status', 'id'], $this->indexColumns());
    }

    public function testStillCoversTheForeignKeyColumn(): void
    {
        (new Migration1780000100WidenWebhookDeliveryWebhookStatusIndex())->update($this->connection);

        // The widened index still leads with webhook_id, so fk.webhook_delivery.webhook_id keeps a
        // covering index throughout the DROP+ADD (a bare DROP would be rejected by InnoDB).
        $columns = $this->indexColumns();
        static::assertNotEmpty($columns);
        static::assertSame('webhook_id', $columns[0]);
    }

    /**
     * @return list<string>
     */
    private function indexColumns(): array
    {
        /** @var list<string> $columns */
        $columns = $this->connection->fetchFirstColumn(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index
             ORDER BY SEQ_IN_INDEX',
            ['table' => 'webhook_delivery', 'index' => 'idx.webhook_delivery.webhook_status']
        );

        return $columns;
    }
}
