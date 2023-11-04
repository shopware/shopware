<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1559050903PromotionExclusion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1559050903;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `promotion` ADD `exclusion_ids` JSON  NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
