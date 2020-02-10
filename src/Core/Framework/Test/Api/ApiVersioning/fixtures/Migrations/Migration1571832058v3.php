<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Migrations;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1571832058v3 extends MigrationStep
{
    private const BACKWARD_UPDATE_TRIGGER_NAME = 'Migration1571832058v3UpdateBundleTriggerBackward';
    private const FORWARD_UPDATE_TRIGGER_NAME = 'Migration1571832058v3UpdateBundleTriggerForward';
    private const BACKWARD_INSERT_TRIGGER_NAME = 'Migration1571832058v3InsertBundleTriggerBackward';
    private const FORWARD_INSERT_TRIGGER_NAME = 'Migration1571832058v3InsertBundleTriggerForward';

    public function getCreationTimestamp(): int
    {
        return 1571832058;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE _test_bundle
            MODIFY `name` VARCHAR(255) NULL,
            ADD COLUMN `pseudo_price` DOUBLE NOT NULL DEFAULT 0.0 AFTER `discount`;
        ');

        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `_test_bundle_translation` (
              `_test_bundle_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `name` VARCHAR(255),
              `translated_description` LONGTEXT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`_test_bundle_id`, `language_id`),
              CONSTRAINT `fk.bundle_translation.bundle_id` FOREIGN KEY (`_test_bundle_id`)
                REFERENCES `_test_bundle` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.bundle_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `_test_bundle_price` (
              `id` BINARY(16) NOT NULL,
              `bundle_id` BINARY(16) NOT NULL,
              `price` JSON NOT NULL,
              `quantity_start` INT NOT NULL,
              `quantity_end` INT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `fk.bundle_price.bundle_id` FOREIGN KEY (`bundle_id`)
                REFERENCES `_test_bundle` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `json.bundle_price.price` CHECK (JSON_VALID(`price`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeUpdate('
            INSERT INTO `_test_bundle_translation` (`_test_bundle_id`, `language_id`, `name`, `translated_description`,  `created_at`)
                SELECT id as _test_bundle_id, :languageId as language_id, name, description, NOW()
                FROM _test_bundle;
        ', ['languageId' => Defaults::LANGUAGE_SYSTEM]);

        $this->addBackwardTrigger(
            $connection,
            self::BACKWARD_UPDATE_TRIGGER_NAME,
            '_test_bundle_translation',
            'BEFORE',
            'UPDATE',
            sprintf('
                IF (NEW.language_id = "%s")
                THEN    
                    UPDATE `_test_bundle`
                    SET `name` = NEW.name, `description` = NEW.translated_description
                    WHERE `id` = NEW._test_bundle_id;
                END IF
            ', Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM))
        );
        $this->addForwardTrigger(
            $connection,
            self::FORWARD_UPDATE_TRIGGER_NAME,
            '_test_bundle',
            'BEFORE',
            'UPDATE',
            sprintf(' 
                UPDATE `_test_bundle_translation`
                SET `name` = NEW.name, `translated_description` = NEW.description
                WHERE `_test_bundle_id` = NEW.id AND `language_id` = "%s"
            ', Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM))
        );
        $this->addBackwardTrigger(
            $connection,
            self::BACKWARD_INSERT_TRIGGER_NAME,
            '_test_bundle_translation',
            'BEFORE',
            'INSERT',
            sprintf('
                IF (NEW.language_id = "%s")
                THEN    
                    UPDATE `_test_bundle`
                    SET `name` = NEW.name, `description` = NEW.translated_description
                    WHERE `id` = NEW._test_bundle_id;
                END IF
            ', Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM))
        );
        $this->addForwardTrigger(
            $connection,
            self::FORWARD_INSERT_TRIGGER_NAME,
            '_test_bundle',
            'AFTER',
            'INSERT',
            sprintf(' 
                INSERT INTO `_test_bundle_translation` (`_test_bundle_id`, `languageId`, `name`, `translated_description`,  `created_at`)
                VALUES (NEW.id, "%s", NEW.name, NEW.description, NOW())
            ', Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM))
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->removeTrigger($connection, self::BACKWARD_UPDATE_TRIGGER_NAME);
        $this->removeTrigger($connection, self::FORWARD_UPDATE_TRIGGER_NAME);
        $this->removeTrigger($connection, self::BACKWARD_INSERT_TRIGGER_NAME);
        $this->removeTrigger($connection, self::FORWARD_INSERT_TRIGGER_NAME);

        $connection->executeUpdate('
            ALTER TABLE `_test_bundle` 
            DROP COLUMN `name`,
            DROP COLUMN `description`;
        ');
    }
}
