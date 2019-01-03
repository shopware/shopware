<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1546246191CleanUpPaymentMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1546246191;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeQuery(
            'ALTER TABLE `payment_method`
                DROP `table`,
                DROP `hide`,
                DROP `allow_esd`,
                DROP `used_iframe`,
                DROP `hide_prospect`,
                DROP `action`,
                DROP `source`,
                DROP `mobile_inactive`;'
        );
    }
}
