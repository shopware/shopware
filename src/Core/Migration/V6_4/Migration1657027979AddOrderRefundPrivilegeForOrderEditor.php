<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1657027979AddOrderRefundPrivilegeForOrderEditor extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'order_refund.viewer' => [
            'order_transaction_capture_refund:read',
        ],
        'order_refund.editor' => [
            'order_transaction_capture_refund:update',
        ],
        'order_refund.creator' => [
            'order_transaction_capture_refund:create',
        ],
        'order_refund.deleter' => [
            'order_transaction_capture_refund:delete',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1657027979;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
