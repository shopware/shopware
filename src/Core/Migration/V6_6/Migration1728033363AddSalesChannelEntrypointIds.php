<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1728033363AddSalesChannelEntrypointIds extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1728033363;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'sales_channel', 'entrypoint_ids', 'JSON');
    }
}
