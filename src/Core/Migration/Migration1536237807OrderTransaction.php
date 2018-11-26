<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237807OrderTransaction extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237807;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `order_transaction` (
              `id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `order_id` binary(16) NOT NULL,
              `order_version_id` binary(16) NOT NULL,
              `payment_method_id` binary(16) NOT NULL,
              `order_transaction_state_id` binary(16) NOT NULL,
              `order_transaction_state_version_id` binary(16) NOT NULL,
              `amount` JSON NOT NULL,
              `details` JSON NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `version_id`),
              CONSTRAINT `json.amount` CHECK (JSON_VALID(`amount`)),
              CONSTRAINT `json.details` CHECK (JSON_VALID (`details`)),
              CONSTRAINT `fk.order_transaction.order_id` FOREIGN KEY (`order_id`, `order_version_id`) REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.order_transaction.payment_method_id` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.order_transaction.order_transaction_state_id` FOREIGN KEY (`order_transaction_state_id`, `order_transaction_state_version_id`) REFERENCES `order_transaction_state` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
