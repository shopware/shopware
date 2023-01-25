<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1617974402AddCustomFieldsToApp extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1617974402;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `app_translation` ADD COLUMN `custom_fields` JSON NULL AFTER `privacy_policy_extensions`');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
