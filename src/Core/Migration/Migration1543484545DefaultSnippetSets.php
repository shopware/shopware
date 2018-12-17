<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Struct\Uuid;

class Migration1543484545DefaultSnippetSets extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1543484545;
    }

    public function update(Connection $connection): void
    {
        // second languages
        $connection->insert('snippet_set', [
            'id' => Uuid::fromHexToBytes(Defaults::SNIPPET_BASE_SET_DE),
            'name' => 'BASE de_DE',
            'base_file' => 'messages.de_DE',
            'iso' => 'de_DE',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        $connection->insert('snippet_set', [
            'id' => Uuid::fromHexToBytes(Defaults::SNIPPET_BASE_SET_EN),
            'name' => 'BASE en_GB',
            'base_file' => 'messages.en_GB',
            'iso' => 'en_GB',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
