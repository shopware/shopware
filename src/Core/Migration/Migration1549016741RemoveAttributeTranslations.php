<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1549016741RemoveAttributeTranslations extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1549016741;
    }

    public function update(Connection $connection): void
    {
        // implement update
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('DROP TABLE `attribute_translation`');
    }
}
