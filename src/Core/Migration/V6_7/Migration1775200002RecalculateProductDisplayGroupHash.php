<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Schedules a product index pass that runs only the variant listing updater ({@see ProductIndexer::VARIANT_LISTING_UPDATER}),
 * which recalculates {@see \Shopware\Core\Content\Product\ProductEntity::displayGroup} with the same logic as a full product
 * index. Execution is deferred until post-install/update flows process {@see \Shopware\Core\Framework\Migration\IndexerQueuer}
 * (for example after {@see \Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent}), so migrations stay fast even on
 * large catalogs.
 *
 * To run the same pass immediately on the CLI (skip every {@see ProductIndexer::getOptions()} entry except
 * {@see ProductIndexer::VARIANT_LISTING_UPDATER}, plus {@see ProductIndexer::STATES_UPDATER} when that updater is still active):
 *
 * `bin/console dal:refresh:index --only=product.indexer --skip=product.inheritance,product.stock,product.child-count,product.many-to-many-id-field,product.category-denormalizer,product.cheapest-price,product.rating-average,product.stream,product.search-keyword,product.states`
 *
 * @internal
 */
#[Package('framework')]
class Migration1775200002RecalculateProductDisplayGroupHash extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1775200002;
    }

    public function update(Connection $connection): void
    {
        $this->registerIndexer($connection, 'product.indexer', [ProductIndexer::VARIANT_LISTING_UPDATER]);
    }
}
