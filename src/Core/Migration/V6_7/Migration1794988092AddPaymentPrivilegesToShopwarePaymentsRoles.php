<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1794988092AddPaymentPrivilegesToShopwarePaymentsRoles extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'sw-payments.viewer' => [
            'payment.viewer',
        ],
        // matches what the Administration stores for `payment.editor` on top of the viewer privileges
        'sw-payments.editor' => [
            'payment.editor',
            'payment_method:update',
            'media:create',
            'media:update',
            'media_default_folder:create',
            'media_default_folder:update',
            'media_folder:create',
            'media_folder:delete',
            'media_folder:update',
            'media_folder_configuration:create',
            'media_folder_configuration:delete',
            'media_folder_configuration:update',
            'media_folder_configuration_media_thumbnail_size:create',
            'media_folder_configuration_media_thumbnail_size:delete',
            'media_folder_configuration_media_thumbnail_size:update',
            'media_thumbnail_size:create',
            'media_thumbnail_size:delete',
            'media_thumbnail_size:update',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1794988092;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }
}
