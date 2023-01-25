<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
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
