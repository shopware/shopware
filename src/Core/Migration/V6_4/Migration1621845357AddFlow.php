<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1621845357AddFlow extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1621845357;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `flow` (
                `id`                    BINARY(16)      NOT NULL,
                `name`                  VARCHAR(255)    COLLATE utf8mb4_unicode_ci  NOT NULL,
                `description`           MEDIUMTEXT      COLLATE utf8mb4_unicode_ci  NULL,
                `event_name`            VARCHAR(255)    NOT NULL,
                `priority`              INT(11)         NOT NULL DEFAULT 1,
                `payload`               LONGBLOB        NULL,
                `invalid`               TINYINT(1)      NOT NULL DEFAULT 0,
                `active`                TINYINT(1)      NOT NULL DEFAULT 0,
                `custom_fields`         JSON            NULL,
                `created_at`            DATETIME(3)     NOT NULL,
                `updated_at`            DATETIME(3)     NULL,
                PRIMARY KEY (`id`),
                INDEX `idx.flow.event_name` (`event_name`, `priority`),
                CONSTRAINT `json.flow.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
