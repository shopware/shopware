<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('business-ops')]
class Migration1659256999CreateFlowTemplateTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1659256999;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `flow_template` (
                `id`                    BINARY(16)      NOT NULL,
                `name`                  VARCHAR(255)    COLLATE utf8mb4_unicode_ci  NOT NULL,
                `config`                JSON            NULL,
                `created_at`            DATETIME(3)     NOT NULL,
                `updated_at`            DATETIME(3)     NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `json.flow_template.config` CHECK (JSON_VALID(`config`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
