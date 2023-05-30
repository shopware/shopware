<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232680Rule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232680;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `rule` (
              `id`              BINARY(16)      NOT NULL,
              `name`            VARCHAR(500)    NOT NULL,
              `description`     LONGTEXT        NULL,
              `priority`        INT(11)         NOT NULL,
              `payload`         LONGBLOB        NULL,
              `invalid`         TINYINT(1)      NOT NULL DEFAULT 0,
              `module_types`    JSON            NULL,
              `custom_fields`   JSON            NULL,
              `created_at`      DATETIME(3)     NOT NULL,
              `updated_at`      DATETIME(3)     NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `json.rule.module_types` CHECK (JSON_VALID(`module_types`)),
              CONSTRAINT `json.rule.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
