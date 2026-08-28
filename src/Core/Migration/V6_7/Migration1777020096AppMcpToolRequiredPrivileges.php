<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\AddColumnTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1777020096AppMcpToolRequiredPrivileges extends MigrationStep
{
    use AddColumnTrait;

    public function getCreationTimestamp(): int
    {
        return 1777020096;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'app_mcp_tool', 'required_privileges', 'JSON');
    }
}
