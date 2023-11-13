<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Moved to \Shopware\Elasticsearch\Migration\V6_5\Migration1689083660ElasticsearchIndexTask
 *
 * @internal
 */
#[Package('core')]
class Migration1561442979ElasticsearchIndexTask extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1561442979;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
