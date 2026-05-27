<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1779893783AddBusinessTimeZoneToSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779893783;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, SalesChannelDefinition::ENTITY_NAME, 'business_time_zone')) {
            return;
        }

        $this->addColumn($connection, SalesChannelDefinition::ENTITY_NAME, 'business_time_zone', 'VARCHAR(255)');
    }
}
