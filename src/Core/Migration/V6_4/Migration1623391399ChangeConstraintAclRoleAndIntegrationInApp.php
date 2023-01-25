<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1623391399ChangeConstraintAclRoleAndIntegrationInApp extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1623391399;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `app` DROP FOREIGN KEY `fk.app.integration_id`');

        $connection->executeStatement('ALTER TABLE `app` ADD CONSTRAINT `fk.app.integration_id` FOREIGN KEY (`integration_id`) REFERENCES `integration` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');

        $connection->executeStatement('ALTER TABLE `app` DROP FOREIGN KEY `fk.app.acl_role_id`;');

        $connection->executeStatement('ALTER TABLE `app` ADD CONSTRAINT `fk.app.acl_role_id` FOREIGN KEY (`acl_role_id`) REFERENCES `acl_role` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
