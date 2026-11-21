<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1794988092AddPaymentViewerToShopwarePaymentsViewer extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'sw-payments.viewer' => [
            'payment.viewer',
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
