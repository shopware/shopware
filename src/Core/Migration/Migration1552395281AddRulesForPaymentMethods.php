<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1552395281AddRulesForPaymentMethods extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552395281;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE payment_method_rule
            (
                `payment_method_id` BINARY(16) NOT NULL,
                `rule_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3),
                PRIMARY KEY (`payment_method_id`, `rule_id`),
                CONSTRAINT `fk.payment_method_rule.rule_id` FOREIGN KEY (`rule_id`) REFERENCES `rule` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk.payment_method_rule.payment_method_id` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // TODO: check in maria db if Check Constraint is deleted
        $connection->exec('
            ALTER TABLE payment_method
            DROP COLUMN `risk_rules`;
        ');
    }
}
