<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Migration\TriggerCollection\UnidirectionalTriggerCollection;
use Shopware\Core\Framework\Migration\TriggerDirection;

class Migration1537420769AddFileName extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1537420769;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `media`
            ADD COLUMN `file_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL;
        ');

        $connection->executeQuery('
            UPDATE `media`
            SET `file_name` = `id`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    public function getTrigger(): array
    {
        return (new UnidirectionalTriggerCollection(
            'updateMediaFileName',
            TriggerDirection::FORWARD,
            MediaDefinition::getEntityName(),
            'file_name',
            'id'
        ))->getTrigger();
    }
}
