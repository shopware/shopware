<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Struct\Uuid;

class Migration1542703454UpdateSalesChannelIcon extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1542703454;
    }

    public function update(Connection $connection): void
    {
        $storefrontApi = Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_STOREFRONT_API);
        $connection->update('sales_channel_type', ['icon_name' => 'default-shopping-basket', 'updated_at' => date(Defaults::DATE_FORMAT)], ['id' => $storefrontApi]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
