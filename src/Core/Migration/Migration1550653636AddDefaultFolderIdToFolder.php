<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1550653636AddDefaultFolderIdToFolder extends MigrationStep
{
    private const FORWARD_TRIGGER_NAME = 'TRIGGER_1550653636_FORWARD';
    private const BACKWARD_TRIGGER_NAME = 'TRIGGER_1550653636_BACKWARD';

    public function getCreationTimestamp(): int
    {
        return 1550653636;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `media_folder`
            ADD COLUMN `default_folder_id` BINARY(16) AFTER parent_id,
            ADD CONSTRAINT `fk.media_folder.default_folder_id` FOREIGN KEY (`default_folder_id`)
                REFERENCES `media_default_folder` (`id`) ON DELETE SET NULL;
            '
        );

        $connection->exec('
            UPDATE `media_folder` 
            SET default_folder_id = (SELECT id FROM media_default_folder WHERE media_folder_id = media_folder.id)
        ');

        $this->addForwardTrigger(
            $connection,
            self::FORWARD_TRIGGER_NAME . '_INSERT',
            'media_default_folder',
            'AFTER',
            'INSERT',
            'IF (NEW.media_folder_id IS NOT NULL) THEN
                    UPDATE media_folder SET default_folder_id = NEW.id WHERE id = NEW.media_folder_id;
                END IF'
        );

        $this->addForwardTrigger(
            $connection,
            self::FORWARD_TRIGGER_NAME . '_UPDATE',
            'media_default_folder',
            'AFTER',
            'UPDATE',
            'IF (NEW.media_folder_id IS NOT NULL) THEN
                    UPDATE media_folder SET default_folder_id = NEW.id WHERE id = NEW.media_folder_id;
                END IF'
        );

        $this->addBackwardTrigger(
            $connection,
            self::BACKWARD_TRIGGER_NAME . '_INSERT',
            'media_folder',
            'AFTER',
            'INSERT',
            'IF (NEW.default_folder_id IS NOT NULL) THEN
                    UPDATE media_default_folder SET media_folder_id = NEW.id WHERE id = NEW.default_folder_id;
                END IF'
        );

        $this->addBackwardTrigger(
            $connection,
            self::BACKWARD_TRIGGER_NAME . '_UPDATE',
            'media_folder',
            'AFTER',
            'UPDATE',
            'IF (NEW.default_folder_id IS NOT NULL) THEN
                    UPDATE media_default_folder SET media_folder_id = NEW.id WHERE id = NEW.default_folder_id;
                END IF'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->removeTrigger($connection, self::FORWARD_TRIGGER_NAME . '_INSERT');
        $this->removeTrigger($connection, self::FORWARD_TRIGGER_NAME . '_UPDATE');
        $this->removeTrigger($connection, self::BACKWARD_TRIGGER_NAME . '_INSERT');
        $this->removeTrigger($connection, self::BACKWARD_TRIGGER_NAME . '_UPDATE');

        $connection->exec('
            ALTER TABLE `media_default_folder`
            DROP FOREIGN KEY `fk.media_default_folder.media_folder_id`,
            DROP COLUMN `media_folder_id`;
            '
        );
    }
}
