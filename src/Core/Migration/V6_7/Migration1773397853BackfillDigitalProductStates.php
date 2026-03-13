<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1773397853BackfillDigitalProductStates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773397853;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'product', 'type') || !TableHelper::columnExists($connection, 'product', 'states')) {
            return;
        }

        $batchSize = 5000;

        do {
            $affected = $connection->executeStatement(
                <<<'SQL'
                UPDATE `product`
                SET `states` = :states,
                    `updated_at` = :updatedAt
                WHERE `type` = :digitalType
                  AND `states` IS NULL
                LIMIT 5000
                SQL,
                [
                    'states' => json_encode([State::IS_DOWNLOAD], \JSON_THROW_ON_ERROR),
                    'digitalType' => ProductDefinition::TYPE_DIGITAL,
                    'updatedAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
                ]
            );
        } while ($affected >= $batchSize);
    }
}
