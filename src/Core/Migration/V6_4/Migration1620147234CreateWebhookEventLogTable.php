<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1620147234CreateWebhookEventLogTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1620147234;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `webhook_event_log` (
                `id` BINARY(16) NOT NULL,
                `app_name` VARCHAR(255) NOT NULL,
                `webhook_name` VARCHAR(255) NOT NULL,
                `event_name` VARCHAR(255) NOT NULL,
                `delivery_status` VARCHAR(255) NOT NULL,
                `timestamp` INT(11) NULL,
                `processing_time` INT(11) NULL,
                `app_version` VARCHAR(255) NOT NULL,
                `request_content` JSON NULL,
                `response_content` JSON NULL,
                `response_status_code` INT(11) NULL,
                `response_reason_phrase` VARCHAR(255) NULL,
                `url` VARCHAR(500) NOT NULL,
                `serialized_webhook_message` LONGBLOB NOT NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `json.webhook_event_log.request_content` CHECK (JSON_VALID(`request_content`)),
                CONSTRAINT `json.webhook_event_log.response_content` CHECK (JSON_VALID(`response_content`)),
                CONSTRAINT `json.webhook_event_log.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
