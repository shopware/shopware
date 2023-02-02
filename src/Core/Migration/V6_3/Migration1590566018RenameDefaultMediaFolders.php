<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1590566018RenameDefaultMediaFolders extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1590566018;
    }

    public function update(Connection $connection): void
    {
        // rename 'CMS Page Media' folder
        if ($this->checkIfFolderExists('Import Media', $connection)) {
            $connection->exec('UPDATE media_folder SET name = \'Imported Media\' WHERE name = \'Import Media\' AND updated_at IS NULL');
        }

        // rename 'Imported Media' folder
        if ($this->checkIfFolderExists('Cms Page Media', $connection)) {
            $connection->exec('UPDATE media_folder SET name = \'CMS Media\' WHERE name = \'Cms Page Media\' AND updated_at IS NULL');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function checkIfFolderExists(string $folderName, Connection $connection): bool
    {
        $statement = $connection->prepare('SELECT id FROM media_folder WHERE name = ?');
        $statement->execute([$folderName]);

        return $statement->fetchColumn() ? true : false;
    }
}
