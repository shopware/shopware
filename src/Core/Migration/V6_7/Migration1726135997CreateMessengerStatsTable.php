<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1726135997CreateMessengerStatsTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1726135997;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `messenger_stats` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                `message_type` VARCHAR(255) NOT NULL,
                `time_in_queue` INT NOT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                INDEX idx_created_at(created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
