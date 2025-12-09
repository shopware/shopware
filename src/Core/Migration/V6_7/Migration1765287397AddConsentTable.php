<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\System\Consent\ConsentRepository;
use Shopware\Core\System\Consent\Storage\AdminUserStorage;
use Shopware\Core\System\Consent\Storage\GlobalStorage;

/**
 * @internal
 */
#[Package('framework')]
class Migration1765287397AddConsentTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1765287397;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `consent` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `storage` VARCHAR(50) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.consent.name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $consentRepo = new ConsentRepository($connection);
        $consentRepo->create('tracking_consent', AdminUserStorage::code());
        $consentRepo->create('backend_data_consent', GlobalStorage::code());
    }
}
