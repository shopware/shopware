<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class Migration1758612662UpdateDateRangeRuleDateTimeFormat extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1758612662;
    }

    public function update(Connection $connection): void
    {
        $connection->createQueryBuilder()
            ->update('rule_condition')
            ->set('value', 'REPLACE(value, \'+00:00\', \'\')')
            ->where('type = :type')
            ->andWhere('value LIKE :value')
            ->setParameter('type', 'dateRange')
            ->setParameter('value', '%+00:00%')
            ->executeStatement();

        $this->registerIndexer($connection, 'rule.indexer');
    }
}
