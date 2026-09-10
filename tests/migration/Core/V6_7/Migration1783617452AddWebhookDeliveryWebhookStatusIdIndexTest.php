<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1783617452AddWebhookDeliveryWebhookStatusIdIndex;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1783617452AddWebhookDeliveryWebhookStatusIdIndex::class)]
class Migration1783617452AddWebhookDeliveryWebhookStatusIdIndexTest extends TestCase
{
    private const NEW_INDEX = 'idx.webhook_delivery.webhook_status_id';

    private const LEGACY_INDEX = 'idx.webhook_delivery.webhook_status';

    private const LEGACY_COLUMNS = ['webhook_id', 'delivery_status'];

    private const NEW_COLUMNS = ['webhook_id', 'delivery_status', 'id'];

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testAddsIndexWithoutChangingTheExistingIndexAndCanBeRerun(): void
    {
        $migration = new Migration1783617452AddWebhookDeliveryWebhookStatusIdIndex();

        try {
            $this->restoreLegacyIndex();
            $this->dropNewIndex();

            $migration->update($this->connection);
            $migration->update($this->connection);

            static::assertSame(self::LEGACY_COLUMNS, $this->indexColumns(self::LEGACY_INDEX));
            static::assertSame(self::NEW_COLUMNS, $this->indexColumns(self::NEW_INDEX));
        } finally {
            $this->restoreLegacyIndex();
            $this->restoreNewIndex();
        }
    }

    /**
     * @return list<string>
     */
    private function indexColumns(string $index): array
    {
        /** @var list<string> $columns */
        $columns = $this->connection->fetchFirstColumn(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index
             ORDER BY SEQ_IN_INDEX',
            ['table' => 'webhook_delivery', 'index' => $index]
        );

        return $columns;
    }

    private function dropNewIndex(): void
    {
        if ($this->indexColumns(self::NEW_INDEX) === []) {
            return;
        }

        $this->connection->executeStatement(
            'DROP INDEX `idx.webhook_delivery.webhook_status_id` ON `webhook_delivery`'
        );
    }

    private function restoreLegacyIndex(): void
    {
        $columns = $this->indexColumns(self::LEGACY_INDEX);

        if ($columns === self::LEGACY_COLUMNS) {
            return;
        }

        if ($columns === []) {
            $this->connection->executeStatement(
                'ALTER TABLE `webhook_delivery`
                    ADD INDEX `idx.webhook_delivery.webhook_status` (`webhook_id`, `delivery_status`)'
            );

            return;
        }

        $this->connection->executeStatement(
            'ALTER TABLE `webhook_delivery`
                DROP INDEX `idx.webhook_delivery.webhook_status`,
                ADD INDEX `idx.webhook_delivery.webhook_status` (`webhook_id`, `delivery_status`)'
        );
    }

    private function restoreNewIndex(): void
    {
        $columns = $this->indexColumns(self::NEW_INDEX);

        if ($columns === self::NEW_COLUMNS) {
            return;
        }

        if ($columns !== []) {
            $this->dropNewIndex();
        }

        $this->connection->executeStatement(
            'CREATE INDEX `idx.webhook_delivery.webhook_status_id`
                ON `webhook_delivery` (`webhook_id`, `delivery_status`, `id`)'
        );
    }
}
