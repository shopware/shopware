<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1552546989AddShippingRuleData extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552546989;
    }

    public function update(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `shipping_method`
             ADD `availability_rule_ids` MEDIUMTEXT NULL,
             ADD CONSTRAINT `json.availability_rule_ids` CHECK (JSON_VALID(`availability_rule_ids`));'
        );

        $defaultShippingMethod = $connection->executeQuery('SELECT id FROM shipping_method WHERE active = 1 ORDER BY `position`')->fetchColumn();

        // TODO: When merging migrations --> Add to Migration1536233420BasicData
        $ruleId = Uuid::randomBytes();
        $connection->insert('rule', ['id' => $ruleId, 'name' => 'Cart >= 0', 'priority' => 100, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->insert('rule_condition', ['id' => Uuid::randomBytes(), 'rule_id' => $ruleId, 'type' => 'cartCartAmount', 'value' => json_encode(['operator' => '>=', 'amount' => 0])]);
        $connection->insert('shipping_method_rule', ['shipping_method_id' => $defaultShippingMethod, 'rule_id' => $ruleId, 'created_at' => date(Defaults::STORAGE_DATE_FORMAT)]);
        $connection->update('shipping_method', ['availability_rule_ids' => json_encode([])], ['1' => '1']);
        $connection->update('shipping_method', ['availability_rule_ids' => json_encode([Uuid::fromBytesToHex($ruleId)])], ['id' => $defaultShippingMethod]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}
