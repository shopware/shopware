<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1633422057AddSalutationPrivilegeForOrderViewerRole extends MigrationStep
{
    private const PRIVILEGES = [
        'order.viewer' => [
            'salutation:read',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1633422057;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::PRIVILEGES);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
