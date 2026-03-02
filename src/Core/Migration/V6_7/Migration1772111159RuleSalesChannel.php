<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1772111159RuleSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1772111159;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::tableExists($connection, 'rule_sales_channel')) {
            return;
        }

        $query = <<<'SQL'
            CREATE TABLE rule_sales_channel (
                rule_id BINARY(16) NOT NULL,
                sales_channel_id BINARY(16) NOT NULL,
                PRIMARY KEY (rule_id, sales_channel_id)
            ) DEFAULT CHARACTER SET utf8mb4;

            ALTER TABLE rule_sales_channel
                ADD CONSTRAINT fk__rule_sales_channel__rule_id
                    FOREIGN KEY (rule_id) REFERENCES rule (id) ON UPDATE CASCADE ON DELETE CASCADE;

            ALTER TABLE rule_sales_channel
                ADD CONSTRAINT fk__rule_sales_channel__sales_channel_id
                    FOREIGN KEY (sales_channel_id) REFERENCES sales_channel (id) ON UPDATE CASCADE ON DELETE CASCADE;
        SQL;

        $connection->executeStatement($query);
    }

    public function updateDestructive(Connection $connection): void
    {
        // Add destructive update if necessary
    }
}
