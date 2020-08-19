<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1597928854AddOrderRefund extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1597928854;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            CREATE TABLE `order_refund` (
              `id` BINARY(16) NOT NULL,
              `version_id` BINARY(16) NOT NULL,
              `order_id` BINARY(16) NOT NULL,
              `order_version_id` BINARY(16) NOT NULL,
              `state_id` BINARY(16) NOT NULL,
              `payment_method_id` BINARY(16) NOT NULL,
              `transaction_id` BINARY(16) NULL,
              `transaction_version_id` BINARY(16) NULL,
              `amount` DOUBLE NOT NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`, `version_id`),
              INDEX `idx.state_index` (`state_id`),
              CONSTRAINT `json.order_refund.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.order_refund.order_id` FOREIGN KEY (`order_id`, `order_version_id`)
                REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.order_refund.state_id` FOREIGN KEY (`state_id`)
                REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.order_refund.payment_method_id` FOREIGN KEY (`payment_method_id`)
                REFERENCES `payment_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.order_refund.transaction_id` FOREIGN KEY (`transaction_id`, `transaction_version_id`)
                REFERENCES `order_transaction` (`id`, `version_id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
