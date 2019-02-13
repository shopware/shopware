<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1550064387RenameAssociationFieldMediaDefaultFolder extends MigrationStep
{
    private const FORWARD_TRIGGER_NAME = 'forward_trigger_1550064387';
    private const BACKWARD_TRIGGER_NAME = 'backward_trigger_1550064387';

    public function getCreationTimestamp(): int
    {
        return 1550064387;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('ALTER TABLE `media_default_folder` ADD COLUMN `association_fields` JSON NOT NULL AFTER `entity`;');
        $connection->exec('ALTER TABLE `media_default_folder` ADD CONSTRAINT `json.association_fields` CHECK (JSON_VALID(`association_fields`));');
        $connection->executeUpdate('UPDATE `media_default_folder` set `association_fields` = `associations`;');

        $this->addForwardTrigger(
            $connection,
            static::FORWARD_TRIGGER_NAME . '_insert',
            'media_default_folder',
            'BEFORE',
            'INSERT',
            'SET NEW.`association_fields` = NEW.`associations`'
        );
        $this->addForwardTrigger(
            $connection,
            static::FORWARD_TRIGGER_NAME . '_update',
            'media_default_folder',
            'BEFORE',
            'UPDATE',
            'SET NEW.`association_fields` = NEW.`associations`'
        );

        $this->addBackwardTrigger(
            $connection,
            static::BACKWARD_TRIGGER_NAME . '_insert',
            'media_default_folder',
            'BEFORE',
            'INSERT',
            'SET NEW.`associations` = NEW.`association_fields`'
        );
        $this->addBackwardTrigger(
            $connection,
            static::BACKWARD_TRIGGER_NAME . '_update',
            'media_default_folder',
            'BEFORE',
            'UPDATE',
            'SET NEW.`associations` = NEW.`association_fields`'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('ALTER TABLE `media_default_folder` DROP COLUMN `associations`');
        $this->removeTrigger($connection, self::FORWARD_TRIGGER_NAME . '_insert');
        $this->removeTrigger($connection, self::FORWARD_TRIGGER_NAME . '_update');
        $this->removeTrigger($connection, self::BACKWARD_TRIGGER_NAME . '_insert');
        $this->removeTrigger($connection, self::BACKWARD_TRIGGER_NAME . '_update');
    }
}
