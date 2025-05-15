<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1747265275AddImitatingUser extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1747265275;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE sales_channel_api_context
                ADD admin_user_id binary(16) NULL AFTER customer_id;
        ');

        $connection->executeStatement('
            ALTER TABLE sales_channel_api_context
                DROP FOREIGN KEY `fk.sales_channel_api_context.sales_channel_id`;
        ');

        $connection->executeStatement('
            ALTER TABLE sales_channel_api_context
                DROP FOREIGN KEY `fk.sales_channel_api_context.customer_id`;
        ');

        $connection->executeStatement('
            DROP INDEX `uniq.sales_channel_api_context.sales_channel_id_customer_id`
                ON sales_channel_api_context;
        ');

        $sql = <<<'SQL'
            ALTER TABLE sales_channel_api_context
                ADD CONSTRAINT `fk.sales_channel_api_context.sales_channel_id`
                    FOREIGN KEY (sales_channel_id) REFERENCES sales_channel (id) ON DELETE CASCADE,
                ADD CONSTRAINT `fk.sales_channel_api_context.customer_id`
                    FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE CASCADE,
                ADD CONSTRAINT `fk.sales_channel_api_context.admin_user_id`
                    FOREIGN KEY (admin_user_id) REFERENCES user (id) ON DELETE CASCADE;
SQL;
        $connection->executeStatement($sql);

        $connection->executeStatement('
            CREATE UNIQUE INDEX `uniq.sales_channel_api_context.sales_channel_customer_admin_id`
                ON sales_channel_api_context (sales_channel_id, customer_id, admin_user_id);
        ');
    }
}
