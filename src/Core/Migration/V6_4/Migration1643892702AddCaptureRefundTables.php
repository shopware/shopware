<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1643892702AddCaptureRefundTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1643892702;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `order_transaction_capture` (
              `id`                              BINARY(16)                      NOT NULL,
              `order_transaction_id`            BINARY(16)                      NOT NULL,
              `order_transaction_version_id`    BINARY(16)                      NOT NULL,
              `state_id`                        BINARY(16)                      NOT NULL,
              `external_reference`              VARCHAR(255)                    NULL,
              `amount`                          JSON                            NOT NULL,
              `custom_fields`                   JSON                            NULL,
              `created_at`                      DATETIME(3)                     NOT NULL,
              `updated_at`                      DATETIME(3)                     NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `json.order_transaction_capture.amount` CHECK (JSON_VALID(`amount`)),
              CONSTRAINT `json.order_transaction_capture.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.order_transaction_capture.order_transaction_id` FOREIGN KEY (`order_transaction_id`, `order_transaction_version_id`)
                REFERENCES `order_transaction` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.order_transaction_capture.state_id` FOREIGN KEY (`state_id`)
                REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `order_transaction_capture_refund` (
              `id`                      BINARY(16)                              NOT NULL,
              `capture_id`              BINARY(16)                              NOT NULL,
              `state_id`                BINARY(16)                              NOT NULL,
              `reason`                  VARCHAR(255)                            NULL,
              `amount`                  JSON                                    NOT NULL,
              `custom_fields`           JSON                                    NULL,
              `external_reference`      VARCHAR(255)                            NULL,
              `created_at`              DATETIME(3)                             NOT NULL,
              `updated_at`              DATETIME(3)                             NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `json.order_transaction_capture_refund.amount` CHECK (JSON_VALID(`amount`)),
              CONSTRAINT `json.order_transaction_capture_refund.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.order_transaction_capture_refund.capture_id` FOREIGN KEY (`capture_id`)
                REFERENCES `order_transaction_capture` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.order_transaction_capture_refund.state_id` FOREIGN KEY (`state_id`)
                REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `order_transaction_capture_refund_position` (
              `id`                              BINARY(16)                              NOT NULL,
              `refund_id`                       BINARY(16)                              NOT NULL,
              `order_line_item_id`              BINARY(16)                              NOT NULL,
              `order_line_item_version_id`      BINARY(16)                              NOT NULL,
              `quantity`                        INT(11)                                 NOT NULL,
              `reason`                          VARCHAR(255)                            NULL,
              `external_reference`              VARCHAR(255)                            NULL,
              `amount`                          JSON                                    NOT NULL,
              `custom_fields`                   JSON                                    NULL,
              `created_at`                      DATETIME(3)                             NOT NULL,
              `updated_at`                      DATETIME(3)                             NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `json.order_transaction_capture_refund_position.amount` CHECK (JSON_VALID(`amount`)),
              CONSTRAINT `json.order_transaction_capture_refund_position.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.order_transaction_capture_refund_position.refund_id` FOREIGN KEY (`refund_id`)
                REFERENCES `order_transaction_capture_refund` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.order_transaction_capture_refund_position.order_line_item_id` FOREIGN KEY (`order_line_item_id`, `order_line_item_version_id`)
                REFERENCES `order_line_item` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
