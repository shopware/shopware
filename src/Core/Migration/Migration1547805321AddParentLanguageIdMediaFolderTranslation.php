<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1547805321AddParentLanguageIdMediaFolderTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1547805321;
    }

    public function update(Connection $connection): void
    {
        $drop = sprintf('
            ALTER TABLE `media_folder_translation`
            DROP INDEX `fk.media_folder_translation.language_id`,
            DROP FOREIGN KEY `fk.media_folder_translation.language_id`'
        );
        $connection->executeQuery($drop);

        $addQuery = sprintf('
            ALTER TABLE `media_folder_translation`
            ADD COLUMN `language_parent_id` binary(16) NULL AFTER `language_id`,
            ADD CONSTRAINT `fk.media_folder_translation.language_id`
              FOREIGN KEY (`language_id`, `language_parent_id`)
              REFERENCES `language` (`id`, `parent_id`) ON DELETE CASCADE ON UPDATE CASCADE,
            ADD CONSTRAINT `fk.media_folder_translation.language_parent_id` 
              FOREIGN KEY (`media_folder_id`, `language_parent_id`)
              REFERENCES `media_folder_translation` (media_folder_id, language_id) ON DELETE CASCADE ON UPDATE NO ACTION'
        );

        $connection->executeQuery($addQuery);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
