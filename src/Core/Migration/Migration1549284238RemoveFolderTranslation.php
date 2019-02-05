<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Struct\Uuid;

class Migration1549284238RemoveFolderTranslation extends MigrationStep
{
    public const FORWARD_TRIGGER_NAME_FOLDER_TRANSLATION_INSERT =
        'trigger_1549284238_insert_folder_translation';
    public const FORWARD_TRIGGER_NAME_FOLDER_TRANSLATION_UPDATE =
        'trigger_1549284238_update_folder_translation';

    public const BACKWARD_TRIGGER_NAME_MEDIA_FOLDER_INSERT =
        'trigger_1549284238_insert_media_folder';
    public const BACKWARD_TRIGGER_NAME_MEDIA_FOLDER_UPDATE =
        'trigger_1549284238_update_media_folder';

    public function getCreationTimestamp(): int
    {
        return 1549284238;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `media_folder`
            ADD COLUMN `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci AFTER `parent_id`;
        ');

        $connection->executeUpdate('
            UPDATE `media_folder`
            SET `name` = (
              SELECT `name` 
              FROM `media_folder_translation`
              WHERE `media_folder_translation`.`media_folder_id` = `media_folder`.`id` AND 
              `media_folder_translation`.`language_id` = ?
            )
        ', [Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]);

        $this->addForwardTrigger(
            $connection,
            self::FORWARD_TRIGGER_NAME_FOLDER_TRANSLATION_INSERT,
            'media_folder_translation',
            'AFTER',
            'INSERT',
            'IF (NEW.language_id = "' .
                Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM) . '") THEN
                UPDATE `media_folder`
                SET `name` = NEW.name
                WHERE `id` = NEW.media_folder_id;
            END IF'
        );
        $this->addForwardTrigger(
            $connection,
            self::FORWARD_TRIGGER_NAME_FOLDER_TRANSLATION_UPDATE,
            'media_folder_translation',
            'AFTER',
            'UPDATE',
            'IF (NEW.language_id = "' .
            Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM) . '") THEN
                UPDATE `media_folder`
                SET `name` = NEW.name
                WHERE `id` = NEW.media_folder_id;
            END IF'
        );

        $this->addBackwardTrigger(
            $connection,
            self::BACKWARD_TRIGGER_NAME_MEDIA_FOLDER_INSERT,
            'media_folder',
            'AFTER',
            'INSERT',
            'INSERT INTO `media_folder_translation` (media_folder_id, language_id, name)
                VALUES(NEW.id, "' . Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM) . '", NEW.name)'
        );
        $this->addBackwardTrigger(
            $connection,
            self::BACKWARD_TRIGGER_NAME_MEDIA_FOLDER_UPDATE,
            'media_folder',
            'AFTER',
            'UPDATE',
            'UPDATE `media_folder_translation`
                SET `name` = NEW.name
                WHERE media_folder_id = NEW.id AND 
                language_id = "' . Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM) . '"'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->removeTrigger($connection, self::FORWARD_TRIGGER_NAME_FOLDER_TRANSLATION_INSERT);
        $this->removeTrigger($connection, self::FORWARD_TRIGGER_NAME_FOLDER_TRANSLATION_UPDATE);
        $this->removeTrigger($connection, self::BACKWARD_TRIGGER_NAME_MEDIA_FOLDER_UPDATE);
        $this->removeTrigger($connection, self::BACKWARD_TRIGGER_NAME_MEDIA_FOLDER_INSERT);

        $connection->exec('
            DROP TABLE media_folder_translation;
        ');
    }
}
