<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1605530777PrivacyPolicyExtensionsToAppTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1605530777;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `app_translation`
            ADD `privacy_policy_extensions` MEDIUMTEXT NULL AFTER `description`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
