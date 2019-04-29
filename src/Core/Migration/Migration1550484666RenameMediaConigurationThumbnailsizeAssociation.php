<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1550484666RenameMediaConigurationThumbnailsizeAssociation extends MigrationStep
{
    const FORWARD_TRIGGER_NAME = 'TRIGGER_1550484666_FORWARD';
    const BACKWARD_TRIGGER_NAME = 'TRIGGER_1550484666_BACKWARD';

    public function getCreationTimestamp(): int
    {
        return 1550484666;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE `media_folder_configuration_media_thumbnail_size` (
              `media_folder_configuration_id` BINARY(16) NOT NULL,
              `media_thumbnail_size_id` BINARY(16) NOT NULL,
              PRIMARY KEY (`media_folder_configuration_id`, `media_thumbnail_size_id`),
              CONSTRAINT `fk.media_folder_configuration_media_thumbnail_size.conf_id` FOREIGN KEY (`media_folder_configuration_id`)
                REFERENCES `media_folder_configuration` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk.media_folder_configuration_media_thumbnail_size.size_id` FOREIGN KEY (`media_thumbnail_size_id`)
                REFERENCES `media_thumbnail_size` (`id`) ON DELETE CASCADE
            );
        ');

        $connection->executeQuery('INSERT INTO `media_folder_configuration_media_thumbnail_size` SELECT * FROM `media_folder_configuration_thumbnail_size`');

        $this->addForwardTrigger(
            $connection,
            self::FORWARD_TRIGGER_NAME . '_insert',
            'media_folder_configuration_thumbnail_size',
            'AFTER',
            'INSERT',
            'INSERT INTO `media_folder_configuration_media_thumbnail_size`
                           VALUES(NEW.`media_folder_configuration_id`, NEW.`media_thumbnail_size_id`)'
        );

        $this->addForwardTrigger(
            $connection,
            self::FORWARD_TRIGGER_NAME . '_delete',
            'media_folder_configuration_thumbnail_size',
            'AFTER',
            'DELETE',
            'DELETE FROM `media_folder_configuration_media_thumbnail_size`
                   WHERE `media_folder_configuration_id` = OLD.`media_folder_configuration_id`
                   AND `media_thumbnail_size_id` = OLD.`media_thumbnail_size_id`'
        );

        $this->addBackwardTrigger(
            $connection,
            self::BACKWARD_TRIGGER_NAME . '_insert',
            'media_folder_configuration_media_thumbnail_size',
            'AFTER',
            'INSERT',
            'INSERT INTO `media_folder_configuration_thumbnail_size`
                       VALUES(NEW.`media_folder_configuration_id`, NEW.`media_thumbnail_size_id`)'
        );
        $this->addBackwardTrigger(
            $connection,
            self::BACKWARD_TRIGGER_NAME . '_delete',
            'media_folder_configuration_media_thumbnail_size',
            'AFTER',
            'DELETE',
            'DELETE FROM `media_folder_configuration_thumbnail_size`
                   WHERE `media_folder_configuration_id` = OLD.`media_folder_configuration_id`
                   AND `media_thumbnail_size_id` = OLD.`media_thumbnail_size_id`'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->removeTrigger($connection, self::FORWARD_TRIGGER_NAME . '_insert');
        $this->removeTrigger($connection, self::FORWARD_TRIGGER_NAME . '_delete');
        $this->removeTrigger($connection, self::BACKWARD_TRIGGER_NAME . '_insert');
        $this->removeTrigger($connection, self::BACKWARD_TRIGGER_NAME . '_delete');

        $connection->exec('DROP TABLE media_folder_configuration_thumbnail_size');
    }
}
