<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1788337044RemoveTypeNameFromDocumentBaseConfigSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1788337044;
    }

    public function update(Connection $connection): void
    {

    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'document_base_config_sales_channel', 'type_name');
    }
}
