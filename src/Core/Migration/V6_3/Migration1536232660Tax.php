<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232660Tax extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232660;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `tax` (
              `id`              BINARY(16)                              NOT NULL,
              `tax_rate`        DECIMAL(10, 2)                          NOT NULL,
              `name`            VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `custom_fields`   JSON                                    NULL,
              `created_at`      DATETIME(3)                             NOT NULL,
              `updated_at`      DATETIME(3)                             NULL,
              PRIMARY KEY (`id`),
              KEY `idx.tax.tax` (`tax_rate`),
              CONSTRAINT `json.tax.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
