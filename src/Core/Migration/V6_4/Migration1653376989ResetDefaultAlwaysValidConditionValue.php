<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1653376989ResetDefaultAlwaysValidConditionValue extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1653376989;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE `rule_condition` SET `value` = null WHERE `type` = \'alwaysValid\' AND `value` LIKE \'{"isAlwaysValid": true}\';');

        $this->registerIndexer($connection, 'Swag.RulePayloadIndexer');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
