<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1608722331AddPaymentMethodRefundConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1608722331;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            CREATE TABLE `payment_method_refund_config` (
              `id` BINARY(16) NOT NULL,
              `payment_method_id` BINARY(16) NOT NULL,
              `technical_name` VARCHAR(255) NOT NULL,
              `options` JSON NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              UNIQUE `uniq.payment_method_refund_config.technical_name` (`technical_name`, `payment_method_id`),
              CONSTRAINT `json.payment_method_refund_config.options` CHECK (JSON_VALID(`options`)),
              CONSTRAINT `fk.payment_method_refund_config.payment_method_id` FOREIGN KEY (`payment_method_id`)
                REFERENCES `payment_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeUpdate('
            ALTER TABLE `order_refund`
              ADD COLUMN `options` JSON NULL AFTER `amount`,
              ADD CONSTRAINT `json.order_refund.options` CHECK (JSON_VALID(`options`));
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
