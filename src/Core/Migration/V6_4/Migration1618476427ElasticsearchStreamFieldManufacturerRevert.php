<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1618476427ElasticsearchStreamFieldManufacturerRevert extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1618476427;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE product_stream_filter SET field = "manufacturer.id" WHERE field = "manufacturerId"');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
