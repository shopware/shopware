<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1776600000AddWebhookStreamTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1776600000;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::tableExists($connection, 'webhook_stream')) {
            return;
        }

        $connection->executeStatement('
            CREATE TABLE `webhook_stream` (
                `id`              BINARY(16) NOT NULL,
                `partition_key`   BINARY(16) NOT NULL,
                `locked_by`       VARCHAR(64) NULL,
                `lock_expires_at` DATETIME(3) NULL,
                `last_claimed_at` DATETIME(3) NULL,
                `created_at`      DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.webhook_stream.partition_key` (`partition_key`),
                KEY `idx.webhook_stream.claim` (`last_claimed_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $this->backfillExistingPartitions($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    /**
     * Backfill stream rows for existing webhook_delivery partitions so the new claim service
     * can see deliveries that predate this migration. Without this, rows on partitions that
     * never receive a new event post-upgrade would be stranded.
     */
    private function backfillExistingPartitions(Connection $connection): void
    {
        $partitionKeys = $connection->fetchFirstColumn('SELECT DISTINCT `partition_key` FROM `webhook_delivery`');

        if ($partitionKeys === []) {
            return;
        }

        $createdAt = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $queue = new MultiInsertQueryQueue($connection, 250, true);

        foreach ($partitionKeys as $partitionKey) {
            $queue->addInsert('webhook_stream', [
                'id' => Uuid::randomBytes(),
                'partition_key' => $partitionKey,
                'created_at' => $createdAt,
            ]);
        }

        $queue->execute();
    }
}
