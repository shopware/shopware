<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1552661881CleanUpPaymentMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552661881;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `payment_method_translation`
            ADD COLUMN `surcharge_text` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
            ADD COLUMN `description` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NULL;
        ');

        $connection->exec('
            UPDATE `payment_method_translation` as `translation`
            LEFT JOIN `payment_method` as `method`
            ON `translation`.`payment_method_id` = `method`.`id`
            SET `translation`.`additional_description` = CONCAT("<p>", `translation`.`additional_description`, "</p>"),
            `translation`.`description` = `translation`.`additional_description`,
            `translation`.`surcharge_text` = `method`.`surcharge_string`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE payment_method
            DROP COLUMN `surcharge_string`;
        ');

        $connection->exec('
            ALTER TABLE `payment_method_translation`
            DROP COLUMN `additional_description`;
        ');
    }
}
