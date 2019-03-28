<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1553768322PaymentAvailabilityRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553768322;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `payment_method`
            ADD COLUMN `availability_rule_id` BINARY(16) NULL, 
            ADD COLUMN `media_id` BINARY(16) NULL,
            ADD CONSTRAINT `fk.media_id` FOREIGN KEY (`media_id`)
              REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ');

        // TODO: When merging migrations --> Add to Migration1536233420BasicData
        $connection->exec('
            UPDATE `payment_method_translation` 
            SET `description` = REPLACE(REPLACE(`description`, "<p>", ""), "</p>", "");
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('DROP TABLE `payment_method_rule`');

        $connection->exec('
            ALTER TABLE `payment_method`
            DROP COLUMN `availability_rule_ids`;
        ');
    }
}
