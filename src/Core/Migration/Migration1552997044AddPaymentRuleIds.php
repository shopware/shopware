<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Struct\Uuid;

class Migration1552997044AddPaymentRuleIds extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552997044;
    }

    public function update(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `payment_method`
             ADD `availability_rule_ids` MEDIUMTEXT NULL,
             ADD CONSTRAINT `json.availability_rule_ids` CHECK (JSON_VALID(`availability_rule_ids`));'
        );

        // TODO: When merging migrations --> Add to Migration1536233420BasicData
        $ruleId = Uuid::uuid4()->getBytes();
        $connection->insert('rule', ['id' => $ruleId, 'name' => 'Cart >= 0 (Payment)', 'priority' => 100, 'created_at' => date(Defaults::DATE_FORMAT)]);
        $connection->insert('rule_condition', ['id' => Uuid::uuid4()->getBytes(), 'rule_id' => $ruleId, 'type' => 'cartCartAmount', 'value' => json_encode(['operator' => '>=', 'amount' => 0])]);
        $connection->update('payment_method', ['availability_rule_ids' => json_encode([])], ['1' => '1']);

        $paymentMethodIds = $connection->executeQuery('SELECT id FROM payment_method')->fetchAll(FetchMode::COLUMN);
        foreach ($paymentMethodIds as $paymentMethodId) {
            $connection->insert('payment_method_rule', ['payment_method_id' => $paymentMethodId, 'rule_id' => $ruleId, 'created_at' => date(Defaults::DATE_FORMAT)]);
            $connection->update('payment_method', ['availability_rule_ids' => json_encode([Uuid::fromBytesToHex($ruleId)])], ['id' => $paymentMethodId]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}
