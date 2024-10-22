<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1716285861AddAppSourceType extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1716285861;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'app', 'source_type', 'VARCHAR(20)', false, '\'local\'');
    }
}
