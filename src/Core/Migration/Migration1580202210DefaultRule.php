<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1580202210DefaultRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1580202210;
    }

    public function update(Connection $connection): void
    {
        $idRule = \Shopware\Core\Framework\Uuid\Uuid::randomBytes();
        $idCondition = \Shopware\Core\Framework\Uuid\Uuid::randomBytes();

        $connection->insert('rule', ['id' => $idRule, 'name' => 'Always valid (Default)', 'description' => null, 'priority' => 100, 'invalid' => 0, 'module_types' => null, 'custom_fields' => null, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'updated_at' => null]);
        $connection->insert('rule_condition', ['id' => $idCondition, 'type' => 'alwaysValid', 'rule_id' => $idRule, 'parent_id' => null, 'value' => '{"isAlwaysValid": true}', 'position' => 0, 'custom_fields' => null, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'updated_at' => null]);

        $this->registerIndexer($connection, 'Swag.RulePayloadIndexer');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
