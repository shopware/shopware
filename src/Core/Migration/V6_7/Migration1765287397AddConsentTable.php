<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\System\Consent\ConsentRepository;
use Shopware\Core\System\Consent\ConsentScope;

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
                `scope` VARCHAR(50) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.consent.name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `consent_state` (
                `id` BINARY(16) NOT NULL,
                `consent_id` BINARY(16) NOT NULL,
                `identifier` BINARY(16) NULL,
                `state` VARCHAR(20) NOT NULL,
                `actor_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.consent_state` (`consent_id`, `identifier`),
                CONSTRAINT `fk.consent_state.consent_id` FOREIGN KEY (`consent_id`)
                    REFERENCES `consent` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `consent_state_history` (
                `id` BINARY(16) NOT NULL,
                `consent_id` BINARY(16) NOT NULL,
                `identifier` BINARY(16) NULL,
                `state` VARCHAR(20) NOT NULL,
                `actor_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx.consent_state_history.consent_scope` (`consent_id`, `identifier`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $existingConsents = $connection->fetchFirstColumn('SELECT name FROM consent');
        $consentRepo = new ConsentRepository($connection);

        if (!\in_array('tracking_consent', $existingConsents, true)) {
            $consentRepo->create('tracking_consent', ConsentScope::ADMIN_USER);
        }

        if (!\in_array('backend_data_consent', $existingConsents, true)) {
            $consentRepo->create('backend_data_consent', ConsentScope::GLOBAL);
        }
    }
}
