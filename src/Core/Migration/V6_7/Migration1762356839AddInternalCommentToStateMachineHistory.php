<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1762356839AddInternalCommentToStateMachineHistory extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1762356839;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'state_machine_history', 'internal_comment')) {
            $this->addColumn($connection, 'state_machine_history', 'internal_comment', 'text');
        }
    }
}
