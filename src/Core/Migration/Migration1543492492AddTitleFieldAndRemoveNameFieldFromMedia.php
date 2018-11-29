<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1543492492AddTitleFieldAndRemoveNameFieldFromMedia extends MigrationStep
{
    public const BACKWARD_TRIGGER_NAME = 'trigger_1542105225_media_translation_add_title';

    public function getCreationTimestamp(): int
    {
        return 1543492492;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('ALTER TABLE `media_translation` ADD COLUMN `title` VARCHAR(255) COLLATE  utf8mb4_unicode_ci');

        $this->addBackwardTrigger(
            $connection,
            self::BACKWARD_TRIGGER_NAME,
            'media_translation',
            'BEFORE',
            'INSERT',
            'SET NEW.`name` = HEX(NEW.`media_id`)'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->removeTrigger($connection, self::BACKWARD_TRIGGER_NAME);
        $connection->exec('ALTER TABLE `media_translation` DROP COLUMN `name`');
    }
}
