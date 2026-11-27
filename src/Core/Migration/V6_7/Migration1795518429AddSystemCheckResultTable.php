<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1795518429AddSystemCheckResultTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795518429;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `system_check_result` (
                `name`              VARCHAR(255) NOT NULL,
                `status`            VARCHAR(16)  NOT NULL,
                `healthy`           TINYINT(1)   NULL,
                `message`           LONGTEXT     NOT NULL,
                `extra`             JSON         NULL,
                `checked_at`        DATETIME(3)  NOT NULL,
                PRIMARY KEY (`name`),
                CONSTRAINT `json.system_check_result.extra` CHECK (JSON_VALID(`extra`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
