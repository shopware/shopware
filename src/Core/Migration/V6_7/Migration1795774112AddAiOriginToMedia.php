<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1795774112AddAiOriginToMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795774112;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'media', 'ai_origin', 'VARCHAR(16)');
    }
}
