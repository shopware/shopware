<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1544088598AddAltFieldAndRemoveDescriptionFieldFromMedia extends MigrationStep
{
    public const FORWARD_TRIGGER_NAME = 'trigger_1544088598_media_translation_add_alt';

    public function getCreationTimestamp(): int
    {
        return 1544088598;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `media_translation` 
            ADD COLUMN `alt` VARCHAR(255) COLLATE  utf8mb4_unicode_ci AFTER `language_id`
        ');

        $this->addForwardTrigger(
            $connection,
            self::FORWARD_TRIGGER_NAME,
            'media_translation',
            'BEFORE',
            'INSERT',
            'SET NEW.`alt` = NEW.`description`'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->removeTrigger($connection, self::FORWARD_TRIGGER_NAME);
        $connection->exec('ALTER TABLE `media_translation` DROP COLUMN `description`');
    }
}
