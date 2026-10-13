<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

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

        $hasOwnerless = (bool) $connection->fetchOne(
            'SELECT 1 FROM `webhook` WHERE `app_id` IS NULL AND `owner_user_id` IS NULL AND `owner_integration_id` IS NULL LIMIT 1'
        );
        if (!$hasOwnerless) {
            return;
        }

        // the creator of existing app-less webhooks is unknown, so hand them to a new admin integration to keep them delivering
        $integrationId = Uuid::randomBytes();
        $connection->insert('integration', [
            'id' => $integrationId,
            'label' => 'Legacy webhooks',
            'access_key' => AccessKeyHelper::generateAccessKey('integration'),
            'secret_access_key' => password_hash(AccessKeyHelper::generateSecretAccessKey(), \PASSWORD_DEFAULT),
            'admin' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->executeStatement(
            'UPDATE `webhook`
             SET `owner_integration_id` = :integrationId
             WHERE `app_id` IS NULL
               AND `owner_user_id` IS NULL
               AND `owner_integration_id` IS NULL',
            ['integrationId' => $integrationId]
        );
    }
}
