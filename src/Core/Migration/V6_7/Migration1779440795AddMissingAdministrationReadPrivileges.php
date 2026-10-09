<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1779440795AddMissingAdministrationReadPrivileges extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'order.viewer' => [
            'sales_channel_domain:read',
            'sales_channel_tracking_order:read',
            'media:read',
            'integration:read',
        ],
        'rule.viewer' => [
            'flow:read',
        ],
        'shipping.viewer' => [
            'sales_channel:read',
        ],
        'users_and_permissions.viewer' => [
            'media:read',
            'media_folder:read',
            'integration:read',
            'api_acl_privileges_additional_get',
        ],
        'sales_channel.viewer' => [
            'product:read',
            'property_group:read',
            'property_group_option:read',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1779440795;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }
}
