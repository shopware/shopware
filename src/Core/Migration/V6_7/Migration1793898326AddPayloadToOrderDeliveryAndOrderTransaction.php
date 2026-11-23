<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1793898326AddPayloadToOrderDeliveryAndOrderTransaction extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793898326;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'order_delivery', 'payload', 'JSON');
        $this->addColumn($connection, 'order_transaction', 'payload', 'JSON');
    }
}
