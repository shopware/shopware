<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1542717995AddMediaType extends MigrationStep
{
    public const BACKWARD_TRIGGER_PATCH_MEDIA_CATALOG = 'trigger_1541578215_patch_media_catalog';
    public const BACKWARD_TRIGGER_PATCH_MEDIA_TRANSLATION_CATALOG = 'trigger_1541578215_patch_media_translation_catalog';

    public function getCreationTimestamp(): int
    {
        return 1542717995;
    }

    /**
     * update non-destructive changes
     */
    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `media`
            ADD `type` longtext DEFAULT NULL;
        ');
    }

    /**
     * update destructive changes
     */
    public function updateDestructive(Connection $connection): void
    {
    }
}
