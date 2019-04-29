<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1553593164ShippingAvailabilityRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553593164;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('ALTER TABLE shipping_method 
                           ADD COLUMN `availability_rule_id` BINARY(16) NULL, 
                           ADD COLUMN `media_id` BINARY(16) NULL,
                           ADD CONSTRAINT `fk.shipping_method.media_id` FOREIGN KEY (media_id)
                                        REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                           ADD CONSTRAINT `fk.shipping_method.rule_id` FOREIGN KEY (`availability_rule_id`)
                                        REFERENCES `rule` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');

        $ruleId = Uuid::randomBytes();
        $connection->insert('rule', ['id' => $ruleId, 'name' => 'Cart >= 0', 'priority' => 100, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('rule_condition', ['id' => Uuid::randomBytes(), 'rule_id' => $ruleId, 'type' => 'cartCartAmount', 'value' => json_encode(['operator' => '>=', 'amount' => 0]), 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->update('shipping_method', ['availability_rule_id' => $ruleId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)], ['1' => '1']);
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('DROP TABLE `shipping_method_rule`');
        $connection->exec('ALTER TABLE `shipping_method` DROP COLUMN `calculation`');
        $connection->exec('ALTER TABLE `shipping_method_translation` DROP COLUMN `comment`');
    }
}
