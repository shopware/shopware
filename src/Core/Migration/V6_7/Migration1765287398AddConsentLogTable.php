<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1765287398AddConsentLogTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1765287398;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `consent_log` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                `consent_name` VARCHAR(100) NOT NULL,
                `timestamp` DATETIME(3) NOT NULL,
                `message` LONGTEXT NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx.consent_log.history` (`consent_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
