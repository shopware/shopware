<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1616496610CheapestPriceCustomProductGroups extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1616496610;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE product_stream_filter SET field = "cheapestPrice" WHERE field = "price"');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
