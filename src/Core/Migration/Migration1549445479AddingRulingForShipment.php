<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1549445479AddingRulingForShipment extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1549445479;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE shipping_method_rule
            (
                `shipping_method_id` BINARY(16) NOT NULL,
                `rule_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3),
                PRIMARY KEY (`shipping_method_id`, `rule_id`),
                CONSTRAINT `fk.shipping_method_rule.rule_id` FOREIGN KEY (`rule_id`) REFERENCES `rule` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk.shipping_method_rule.shipping_method_id` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_method` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
