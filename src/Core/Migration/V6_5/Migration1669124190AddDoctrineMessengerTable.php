<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1669124190AddDoctrineMessengerTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1669124190;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            '
            CREATE TABLE IF NOT EXISTS `messenger_messages` (
              `id` bigint NOT NULL AUTO_INCREMENT,
              `body` longtext NOT NULL,
              `headers` longtext NOT NULL,
              `queue_name` varchar(190) NOT NULL,
              `created_at` datetime NOT NULL,
              `available_at` datetime NOT NULL,
              `delivered_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              INDEX (`queue_name`),
              INDEX (`available_at`),
              INDEX (`delivered_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            '
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `dead_message`');
    }
}
