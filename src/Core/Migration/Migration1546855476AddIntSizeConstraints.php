<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1546855476AddIntSizeConstraints extends MigrationStep
{
    public const FORWARD_TRIGGER_NAME_FOLDER_CONFIGURATION_INSERT =
        'trigger_1546855476_insert_folder_configuration_add_constraints';
    public const FORWARD_TRIGGER_NAME_FOLDER_CONFIGURATION_UPDATE =
        'trigger_1546855476_update_folder_configuration_add_constraints';

    public const FORWARD_TRIGGER_NAME_THUMBNAIL_SIZE_INSERT =
        'trigger_1546855476_insert_thumbnail_size_add_constraints';
    public const FORWARD_TRIGGER_NAME_THUMBNAIL_SIZE_UPDATE =
        'trigger_1546855476_update_thumbnail_size_add_constraints';

    public function getCreationTimestamp(): int
    {
        return 1546855476;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            UPDATE `media_folder_configuration`
            SET thumbnail_quality = 100
            WHERE thumbnail_quality > 100;
        ');

        $connection->exec('
            UPDATE `media_folder_configuration`
            SET thumbnail_quality = 0
            WHERE thumbnail_quality < 0;
        ');

        $this->addForwardTrigger(
            $connection,
            self::FORWARD_TRIGGER_NAME_FOLDER_CONFIGURATION_INSERT,
            'media_folder_configuration',
            'BEFORE',
            'INSERT',
            'IF (NEW.thumbnail_quality > 100) THEN
                SET NEW.thumbnail_quality = 100;
            END IF;
            IF (NEW.thumbnail_quality < 0) THEN
                SET NEW.thumbnail_quality = 0;
            END IF'
        );
        $this->addForwardTrigger(
            $connection,
            self::FORWARD_TRIGGER_NAME_FOLDER_CONFIGURATION_UPDATE,
            'media_folder_configuration',
            'BEFORE',
            'UPDATE',
            'IF (NEW.thumbnail_quality > 100) THEN
                SET NEW.thumbnail_quality = 100;
            END IF;
            IF (NEW.thumbnail_quality < 0) THEN
                SET NEW.thumbnail_quality = 0;
            END IF'
        );

        $this->addForwardTrigger(
            $connection,
            self::FORWARD_TRIGGER_NAME_THUMBNAIL_SIZE_INSERT,
            'media_thumbnail_size',
            'BEFORE',
            'INSERT',
            'IF (NEW.width < 1) THEN
                SET NEW.width = 1;
            END IF;
            IF (NEW.height < 1) THEN
                SET NEW.height = 1;
            END IF'
        );
        $this->addForwardTrigger(
            $connection,
            self::FORWARD_TRIGGER_NAME_THUMBNAIL_SIZE_UPDATE,
            'media_thumbnail_size',
            'BEFORE',
            'UPDATE',
            'IF (NEW.width < 1) THEN
                SET NEW.width = 1;
            END IF;
            IF (NEW.height < 1) THEN
                SET NEW.height = 1;
            END IF'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `media_folder_configuration`
            ADD CONSTRAINT `min_max.thumbnail_quality` CHECK (thumbnail_quality > 0 AND thumbnail_quality < 100);
        ');

        $connection->exec('
            ALTER TABLE `media_thumbnail_size`
            ADD CONSTRAINT `min.width` CHECK (width >= 1);
            ADD CONSTRAINT `min.height` CHECK (height >= 1);
        ');

        $this->removeTrigger($connection, self::FORWARD_TRIGGER_NAME_FOLDER_CONFIGURATION_INSERT);
        $this->removeTrigger($connection, self::FORWARD_TRIGGER_NAME_FOLDER_CONFIGURATION_UPDATE);
        $this->removeTrigger($connection, self::FORWARD_TRIGGER_NAME_THUMBNAIL_SIZE_INSERT);
        $this->removeTrigger($connection, self::FORWARD_TRIGGER_NAME_THUMBNAIL_SIZE_UPDATE);
    }
}
