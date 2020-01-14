<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1578648299ReindexSeoUrls extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1578648299;
    }

    public function update(Connection $connection): void
    {
        $this->registerIndexer($connection, 'Swag.SeoUrlIndexer');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
