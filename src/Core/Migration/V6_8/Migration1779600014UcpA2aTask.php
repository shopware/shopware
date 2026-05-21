<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Persists A2A messages + tasks so:
 *   - `messageId`-based idempotency works across retries
 *   - `tasks/get` and `tasks/cancel` JSON-RPC methods have something to act on
 *
 * @internal
 */
#[Package('framework')]
class Migration1779600014UcpA2aTask extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779600014;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `ucp_a2a_task` (
                `id`                BINARY(16)    NOT NULL,
                `sales_channel_id`  BINARY(16)    NOT NULL,
                `message_id`        VARCHAR(190)  NOT NULL,
                `task_id`           VARCHAR(190)  NULL,
                `context_id`        VARCHAR(190)  NULL,
                `state`             VARCHAR(32)   NOT NULL DEFAULT 'pending',
                `message_response`  MEDIUMTEXT    NOT NULL,
                `created_at`        DATETIME(3)   NOT NULL,
                `expires_at`        DATETIME(3)   NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.ucp_a2a_task.sc_msg` (`sales_channel_id`, `message_id`),
                KEY `idx.ucp_a2a_task.task_id` (`task_id`),
                KEY `idx.ucp_a2a_task.expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
