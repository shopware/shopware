<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1625816310AddDefaultToCartRuleIds extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1625816310;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE cart SET rule_ids = "[]" WHERE rule_ids = "" OR rule_ids IS NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
