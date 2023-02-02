<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1663402950SetDoubleOptinCustomerActive extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1663402950;
    }

    public function update(Connection $connection): void
    {
        $query = <<<'SQL'
            UPDATE
                customer
            SET
                active = 1
            WHERE
                double_opt_in_registration = 1 AND double_opt_in_confirm_date IS NULL AND active = 0;
        SQL;

        $connection->executeStatement($query);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
