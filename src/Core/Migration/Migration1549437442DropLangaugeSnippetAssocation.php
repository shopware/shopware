<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1549437442DropLangaugeSnippetAssocation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1549437442;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `snippet`
            CHANGE `language_id` `language_id` binary(16) NULL AFTER `id`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `snippet`
            DROP `language_id`;
        ');
    }
}
