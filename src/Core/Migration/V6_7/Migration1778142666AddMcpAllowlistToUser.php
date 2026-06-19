<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1778142666AddMcpAllowlistToUser extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1778142666;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'user', 'mcp_allowlist', 'JSON');
    }
}
