<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1565270366PromotionSetGroupRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1565270366;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `promotion_setgroup_rule` (
                setgroup_id BINARY(16) NOT NULL,
                rule_id BINARY(16) NOT NULL,
                PRIMARY KEY (`setgroup_id`, `rule_id`),
                CONSTRAINT `fk.promotion_setgroup_rule.setgroup_id` FOREIGN KEY (setgroup_id)
                  REFERENCES promotion_setgroup (id) ON DELETE CASCADE,
                CONSTRAINT `fk.promotion_setgroup_rule.rule_id` FOREIGN KEY (rule_id)
                  REFERENCES rule (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
       ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
