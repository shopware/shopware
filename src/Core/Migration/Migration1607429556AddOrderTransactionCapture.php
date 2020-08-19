<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1607429556AddOrderTransactionCapture extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1607429556;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            CREATE TABLE `order_transaction_capture` (
              `id` BINARY(16) NOT NULL,
              `version_id` BINARY(16) NOT NULL,
              `transaction_id` BINARY(16) NOT NULL,
              `transaction_version_id` BINARY(16) NOT NULL,
              `state_id` BINARY(16) NOT NULL,
              `amount` DOUBLE NOT NULL,
              `external_reference` VARCHAR(255) NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`, `version_id`),
              INDEX `idx.state_index` (`state_id`),
              CONSTRAINT `json.order_transaction_capture.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.order_transaction_capture.transaction_id` FOREIGN KEY (`transaction_id`, `transaction_version_id`)
                REFERENCES `order_transaction` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.order_transaction_capture.state_id` FOREIGN KEY (`state_id`)
                REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeUpdate('
            ALTER TABLE `order_refund`
              ADD COLUMN `transaction_capture_id` BINARY(16) NULL AFTER `transaction_version_id`,
              ADD CONSTRAINT `fk.order_refund.transaction_capture_id` FOREIGN KEY (`transaction_capture_id`)
                REFERENCES `order_transaction_capture` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
