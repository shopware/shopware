<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1547624932AddSnippetAuthor extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1547624932;
    }

    public function update(Connection $connection): void
    {
        $connection->exec("
          ALTER TABLE `snippet`
          ADD `author` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT 'undefined';
        ");

        $connection->exec("
          ALTER TABLE `snippet`
          CHANGE `author` `author` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `value`;
        ");
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
