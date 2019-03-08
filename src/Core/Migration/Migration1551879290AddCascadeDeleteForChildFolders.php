<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1551879290AddCascadeDeleteForChildFolders extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1551879290;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `media_folder`
            DROP FOREIGN KEY `fk.media_folder.parent_id`;
        ');

        $connection->exec('
            ALTER TABLE `media_folder`
            ADD CONSTRAINT `fk.media_folder.parent_id` FOREIGN KEY (`parent_id`)
                REFERENCES `media_folder` (`id`) ON DELETE CASCADE;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
