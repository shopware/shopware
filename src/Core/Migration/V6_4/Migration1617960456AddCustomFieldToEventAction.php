<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1617960456AddCustomFieldToEventAction extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1617960456;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'event_action', 'custom_fields')) {
            $connection->executeStatement(
                'ALTER TABLE `event_action`
                ADD COLUMN `custom_fields` JSON NULL AFTER `config`,
                ADD CONSTRAINT `json.event_action.custom_fields` CHECK (JSON_VALID(`custom_fields`));'
            );
        }
    }
}
