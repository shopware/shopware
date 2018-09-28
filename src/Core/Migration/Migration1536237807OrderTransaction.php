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
              `tenant_id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `order_id` binary(16) NOT NULL,
              `order_tenant_id` binary(16) NOT NULL,
              `order_version_id` binary(16) NOT NULL,
              `payment_method_id` binary(16) NOT NULL,
              `payment_method_tenant_id` binary(16) NOT NULL,
              `payment_method_version_id` binary(16) NOT NULL,
              `order_transaction_state_id` binary(16) NOT NULL,
              `order_transaction_state_tenant_id` binary(16) NOT NULL,
              `order_transaction_state_version_id` binary(16) NOT NULL,
              `amount` longtext NOT NULL,
              `details` longtext DEFAULT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `version_id`, `tenant_id`),
              CHECK (JSON_VALID (`details`)),
              CONSTRAINT `fk_order_transaction.order_id` FOREIGN KEY (`order_id`, `order_version_id`, `order_tenant_id`) REFERENCES `order` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_order_transaction.payment_method_id` FOREIGN KEY (`payment_method_id`, `payment_method_version_id`, `payment_method_tenant_id`) REFERENCES `payment_method` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk_order_transaction.order_transaction_state_id` FOREIGN KEY (`order_transaction_state_id`, `order_transaction_state_version_id`, `order_transaction_state_tenant_id`) REFERENCES `order_transaction_state` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
