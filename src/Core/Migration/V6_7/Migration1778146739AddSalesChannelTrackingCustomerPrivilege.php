<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1778146739AddSalesChannelTrackingCustomerPrivilege extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'customer.viewer' => [
            'sales_channel_tracking_customer:read',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1778146739;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }
}
