<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\AddColumnTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1795082277AddIndexTaskTotalCount extends MigrationStep
{
    use AddColumnTrait;

    public function getCreationTimestamp(): int
    {
        return 1795082277;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'elasticsearch_index_task', 'total_count', 'INT');
    }
}
