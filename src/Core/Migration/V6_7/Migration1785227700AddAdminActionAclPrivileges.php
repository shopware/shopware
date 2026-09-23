<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1785227700AddAdminActionAclPrivileges extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'system.plugin_maintain' => [
            'system:app:change',
        ],
        'flow.editor' => [
            'flow:dispatch',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1785227700;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }
}
