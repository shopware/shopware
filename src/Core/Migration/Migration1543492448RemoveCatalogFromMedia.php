<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1543492448RemoveCatalogFromMedia extends MigrationStep
{
    public const BACKWARD_TRIGGER_PATCH_MEDIA_CATALOG = 'trigger_1541578215_patch_media_catalog';
    public const BACKWARD_TRIGGER_PATCH_MEDIA_TRANSLATION_CATALOG = 'trigger_1541578215_patch_media_translation_catalog';

    public function getCreationTimestamp(): int
    {
        return 1543492448;
    }

    /**
     * update non-destructive changes
     */
    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `media`
            MODIFY `catalog_id` binary(16) DEFAULT NULL;
            ALTER TABLE `media_translation`
            MODIFY `catalog_id`  binary(16) DEFAULT NULL;
        ');

        $this->addBackwardTrigger(
            $connection,
            self::BACKWARD_TRIGGER_PATCH_MEDIA_CATALOG,
            'media',
            'BEFORE',
            'INSERT',
            '
                SET @default_catalog_id = (SELECT id FROM catalog LIMIT 1);
                SET NEW.`catalog_id` = @default_catalog_id
            '
        );

        $this->addBackwardTrigger(
            $connection,
            self::BACKWARD_TRIGGER_PATCH_MEDIA_TRANSLATION_CATALOG,
            'media_translation',
            'BEFORE',
            'INSERT',
            '
                SET @media_catalog_id = (SELECT `catalog_id` FROM media WHERE media.id = NEW.`media_id` LIMIT 1);
                SET NEW.`catalog_id` = @media_catalog_id
            '
        );
    }

    /**
     * update destructive changes
     */
    public function updateDestructive(Connection $connection): void
    {
        $this->removeTrigger($connection, self::BACKWARD_TRIGGER_PATCH_MEDIA_CATALOG);
        $this->removeTrigger($connection, self::BACKWARD_TRIGGER_PATCH_MEDIA_TRANSLATION_CATALOG);
        $connection->executeQuery('
            ALTER TABLE `media`
            DROP FOREIGN KEY `fk_media.catalog_id`,
            DROP COLUMN `catalog_id`
        ');

        $connection->executeQuery('
            ALTER TABLE `media_translation`
            DROP FOREIGN KEY `media_translation_ibfk_3`,
            DROP COLUMN `catalog_id`
        ');
    }
}
