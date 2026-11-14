<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1794656488AddIdentifierToOrderDeliveryAndOrderTransaction extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794656488;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'order_delivery', 'identifier', 'VARCHAR(255)');
        $this->addColumn($connection, 'order_transaction', 'identifier', 'VARCHAR(255)');
    }
}
