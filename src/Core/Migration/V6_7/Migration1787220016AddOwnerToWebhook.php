<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1787220016AddOwnerToWebhook extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1787220016;
    }

    public function update(Connection $connection): void
    {
        $userColumnAdded = $this->addColumn($connection, 'webhook', 'owner_user_id', 'BINARY(16)');
        if ($userColumnAdded) {
            $connection->executeStatement('ALTER TABLE `webhook` ADD CONSTRAINT `fk.webhook.owner_user_id` FOREIGN KEY (`owner_user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        }

        $integrationColumnAdded = $this->addColumn($connection, 'webhook', 'owner_integration_id', 'BINARY(16)');
        if ($integrationColumnAdded) {
            $connection->executeStatement('ALTER TABLE `webhook` ADD CONSTRAINT `fk.webhook.owner_integration_id` FOREIGN KEY (`owner_integration_id`) REFERENCES `integration` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        }

        $connection->executeStatement(
            'UPDATE `webhook` SET `active` = 0
             WHERE `app_id` IS NULL
               AND `owner_user_id` IS NULL
               AND `owner_integration_id` IS NULL'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
