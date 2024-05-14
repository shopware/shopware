<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1655697288AppFlowEvent extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1655697288;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `app_flow_event` (
                `id`                    BINARY(16) NOT NULL,
                `app_id`                BINARY(16) NOT NULL,
                `name`                  VARCHAR(255) NOT NULL UNIQUE,
                `aware`                 JSON NOT NULL,
                `custom_fields`         JSON            NULL,
                `created_at`            DATETIME(3) NOT NULL,
                `updated_at`            DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `json.app_flow_event.aware` CHECK (JSON_VALID(`aware`)),
                CONSTRAINT `fk.app_flow_event.app_id` FOREIGN KEY (`app_id`)
                    REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `uniq.app_flow_event.name` UNIQUE (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $created = $this->addColumn(
            connection: $connection,
            table: 'flow',
            column: 'app_flow_event_id',
            type: 'BINARY(16)'
        );

        if ($created) {
            $connection->executeStatement(
                'ALTER TABLE `flow`
                ADD CONSTRAINT `fk.flow.app_flow_event_id` FOREIGN KEY (`app_flow_event_id`) REFERENCES `app_flow_event` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;'
            );
        }
    }
}
