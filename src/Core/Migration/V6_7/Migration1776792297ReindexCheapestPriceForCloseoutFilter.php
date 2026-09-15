<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Re-runs the cheapest-price indexer so existing products pick up the new
 * `is_closeout` / `available` fields that are required to exclude hidden
 * closeout variants from the cheapest-price aggregation (see issue #16239).
 *
 * Without this migration, existing `product.cheapest_price_accessor` rows
 * keep the pre-upgrade shape and the new filter silently falls back to the
 * old behaviour until the next product write re-indexes the row.
 *
 * @internal
 */
#[Package('framework')]
class Migration1776792297ReindexCheapestPriceForCloseoutFilter extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1776792297;
    }

    public function update(Connection $connection): void
    {
        $this->registerIndexer($connection, 'product.indexer', ['product.cheapest-price']);
    }
}
