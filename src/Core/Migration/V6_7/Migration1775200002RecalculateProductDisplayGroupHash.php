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
 * Recalculates {@see \Shopware\Core\Content\Product\ProductEntity::displayGroup} via
 * {@see VariantListingUpdater} (same logic as the product indexer’s variant-listing step).
 * Migrations only receive a DB connection, so we call the updater directly instead of
 * {@see \Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer} (which needs the container and message bus).
 *
 * **Performance:** Each batch runs the updater for many parent products; that issues several queries per parent
 * (hide parent, single variant, or grouped listing). Shops with very large variant-parent counts can see long
 * runtimes and sustained write load on `product` during the migration. Plan a maintenance window or staggered
 * rollout if needed. For a separate, operator-triggered pass you could alternatively expose equivalent logic via
 * {@see MigrationStep::updateDestructive()} in a follow-up; this class keeps the work in `update()` for the normal migrate flow.
 *
 * @internal
 */
#[Package('framework')]
class Migration1775200002RecalculateProductDisplayGroupHash extends MigrationStep
{
    private const BATCH_SIZE = 500;

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
                if (!isset($parent['id'])) {
                    continue;
                }

                $hexIds[] = Uuid::fromBytesToHex($parent['id']);
                $lastAutoIncrement = (int) ($parent['auto_increment'] ?? $lastAutoIncrement);
            }

            if ($hexIds !== []) {
                $updater->update($hexIds, $context);
            }
        }
    }
}
