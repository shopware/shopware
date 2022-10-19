<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Migrations will be internal in v6.5.0
 */
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
