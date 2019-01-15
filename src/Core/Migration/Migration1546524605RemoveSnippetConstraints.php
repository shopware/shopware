<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1546524605RemoveSnippetConstraints extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1546524605;
    }

    public function update(Connection $connection): void
    {
        // implement update
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `snippet`
            DROP FOREIGN KEY `fk.snippet.language_id`
        ');

        $connection->exec('
            ALTER TABLE `snippet`
            ADD UNIQUE `snippet_set_id_translation_key` (`snippet_set_id`, `translation_key`),
            DROP INDEX `uniq.language_translation_key`;'
        );

        $connection->exec('
            ALTER TABLE `snippet`
            DROP FOREIGN KEY `fk.snippet.snippet_set_id`,
            ADD FOREIGN KEY (`snippet_set_id`) REFERENCES `snippet_set` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ');
    }
}
