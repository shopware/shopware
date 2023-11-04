<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1631863869AddLogEntryCreateAndStateMachineTransitionReadPrivilege extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'order.viewer' => [
            'state_machine_transition:read',
        ],
        'locale:read' => [
            'log_entry:create', // Add log_entry:create as required privilege
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1631863869;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
