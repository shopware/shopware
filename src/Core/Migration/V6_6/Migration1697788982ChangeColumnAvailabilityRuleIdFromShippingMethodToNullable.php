<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1697788982ChangeColumnAvailabilityRuleIdFromShippingMethodToNullable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1697788982;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `shipping_method` MODIFY COLUMN `availability_rule_id` BINARY(16) DEFAULT NULL');
    }
}
