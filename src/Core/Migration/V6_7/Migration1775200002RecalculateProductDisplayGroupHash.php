<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Recalculates {@see \Shopware\Core\Content\Product\ProductEntity::displayGroup} using
 * {@see VariantListingUpdater} (same behaviour as the product indexer). Runs in batches during
 * {@see MigrationStep::update()}; large catalogs may need a maintenance window.
 *
 * @internal
 */
#[Package('framework')]
class Migration1775200002RecalculateProductDisplayGroupHash extends MigrationStep
{
    private const BATCH_SIZE = 1000;

    public function getCreationTimestamp(): int
    {
        return 1775200002;
    }

    public function update(Connection $connection): void
    {
        $updater = new VariantListingUpdater($connection);
        $context = Context::createDefaultContext();
        $lastAutoIncrement = 0;

        while (true) {
            $parents = $connection->fetchAllAssociative(
                <<<'SQL'
                SELECT
                    parent.id,
                    parent.auto_increment
                FROM product parent
                WHERE parent.parent_id IS NULL
                  AND parent.auto_increment > :lastAutoIncrement
                  AND (
                    parent.display_group IS NOT NULL
                    OR parent.variant_listing_config IS NOT NULL
                    OR EXISTS (
                        SELECT 1
                        FROM product child
                        WHERE child.parent_id = parent.id
                          AND child.parent_version_id = parent.version_id
                    )
                  )
                ORDER BY parent.auto_increment ASC
                LIMIT :limit
                SQL,
                ['lastAutoIncrement' => $lastAutoIncrement, 'limit' => self::BATCH_SIZE],
                ['lastAutoIncrement' => ParameterType::INTEGER, 'limit' => ParameterType::INTEGER]
            );

            if ($parents === []) {
                break;
            }

            $hexIds = [];
            foreach ($parents as $parent) {
                $hexIds[] = Uuid::fromBytesToHex($parent['id']);
                $lastAutoIncrement = (int) ($parent['auto_increment'] ?? $lastAutoIncrement);
            }

            if ($hexIds !== []) {
                $updater->update($hexIds, $context);
            }
        }
    }
}
