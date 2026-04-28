<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1753340103AddSalesChannelContextTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1753340103;
    }

    public function update(Connection $connection): void
    {
        $this->createSalesChannelContextTables($connection);

        $this->migrateSalesChannelApiContextData($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropTableIfExists($connection, 'sales_channel_api_context');
    }

    private function createSalesChannelContextTables(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `sales_channel_context` (
                `id` BINARY(16) NOT NULL,
                `cart_token` VARCHAR(255) NOT NULL,
                `payload` JSON NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NULL,
                `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.sales_channel_context.cart_token`(`cart_token`),
                UNIQUE KEY `uniq.sales_channel_context.sales_channel_id_customer_id`(`sales_channel_id`, `customer_id`),
                CONSTRAINT `json.sales_channel_context.payload` CHECK (JSON_VALID(`payload`)),
                CONSTRAINT `fk.sales_channel_context.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.sales_channel_context.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `sales_channel_context_token` (
                `token` VARCHAR(255) NOT NULL,
                `sales_channel_context_id` BINARY(16) NOT NULL,
                `additional_payload` JSON NULL,
                `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
                PRIMARY KEY (`token`),
                CONSTRAINT `json.sales_channel_context_token.additional_payload` CHECK (JSON_VALID(`additional_payload`)),
                CONSTRAINT `fk.sales_channel_context_token.sales_channel_context_id` FOREIGN KEY (`sales_channel_context_id`) REFERENCES `sales_channel_context` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    private function migrateSalesChannelApiContextData(Connection $connection): void
    {
        $manager = $connection->createSchemaManager();
        if (!$manager->tableExists('sales_channel_context') || !$manager->tableExists('sales_channel_context_token') || !$manager->tableExists('sales_channel_api_context')) {
            return;
        }

        $offset = 0;
        $batchSize = 50000;

        $salesChannelContextBaseSql = 'INSERT INTO sales_channel_context (id, cart_token, payload, sales_channel_id, customer_id, updated_at) VALUES ';
        $salesChannelContextTokenBaseSql = 'INSERT INTO sales_channel_context_token (token, sales_channel_context_id, updated_at) VALUES ';

        while (true) {
            /** @var list<array{token: string, payload: ?string, sales_channel_id: string, customer_id: ?string, updated_at: string}> */
            $rows = $connection->fetchAllAssociative(\sprintf('
                SELECT *
                FROM sales_channel_api_context
                WHERE sales_channel_id IS NOT NULL
                LIMIT %d OFFSET %d
            ', $batchSize, $offset));

            if ($rows === []) {
                break;
            }

            $salesChannelContextData = [];
            $salesChannelContextTokenData = [];

            foreach ($rows as $row) {
                $id = Uuid::randomBytes();

                $salesChannelContextData[] = [
                    'id' => $id,
                    'cart_token' => $row['token'],
                    'payload' => $row['payload'] ?: null,
                    'sales_channel_id' => $row['sales_channel_id'],
                    'customer_id' => $row['customer_id'],
                    'updated_at' => $row['updated_at'],
                ];

                $salesChannelContextTokenData[] = [
                    'token' => $row['token'],
                    'sales_channel_context_id' => $id,
                    'created_at' => $row['updated_at'],
                ];
            }

            foreach (array_chunk($salesChannelContextData, 10000) as $batch) {
                $sql = $salesChannelContextBaseSql;
                $sql .= implode(',', array_fill(0, \count($batch), '(?, ?, ?, ?, ?, ?)'));
                $connection->executeStatement($sql, array_merge(...array_map('array_values', $batch)));
            }

            foreach (array_chunk($salesChannelContextTokenData, 10000) as $batch) {
                $sql = $salesChannelContextTokenBaseSql;
                $sql .= implode(',', array_fill(0, \count($batch), '(?, ?, ?)'));
                $connection->executeStatement($sql, array_merge(...array_map('array_values', $batch)));
            }

            $offset += $batchSize;
        }

        $connection->executeStatement('
            TRUNCATE TABLE sales_channel_api_context;
        ');
    }
}
