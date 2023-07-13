<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Moved to \Shopware\Elasticsearch\Migration\V6_5\Migration1689084023AdminElasticsearchIndexTask
 *
 * @internal
 */
#[Package('core')]
class Migration1667731399AdminElasticsearchIndexTask extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1667731399;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
