<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1619604605FixListingPricesUsage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1619604605;
    }

    public function update(Connection $connection): void
    {
        // fix for remaining `listingPrices` references
        $connection->executeStatement('UPDATE product_stream_filter SET `field` = \'cheapestPrice\' WHERE `field` = \'listingPrices\'');
        $connection->executeStatement('UPDATE product_stream SET `api_filter` = REPLACE(`api_filter`, \'listingPrices",\', \'cheapestPrice",\') WHERE `api_filter` LIKE \'%listingPrices%\'');
        $connection->executeStatement('UPDATE category_translation  SET `slot_config` = REPLACE(`slot_config`, \'listingPrices:\', \'cheapestPrice:\') WHERE `slot_config` LIKE \'%listingPrices:%\'');

        // fix for remaining `purchasePrice` references
        $connection->executeStatement('UPDATE product_stream_filter SET `field` = \'purchasePrices\' WHERE `field` = \'purchasePrice\'');
        $connection->executeStatement('UPDATE product_stream SET `api_filter` = REPLACE(`api_filter`, \'purchasePrice",\', \'purchasePrices",\') WHERE `api_filter` LIKE \'%purchasePrice",%\'');
        $connection->executeStatement('UPDATE category_translation  SET `slot_config` = REPLACE(`slot_config`, \'purchasePrice:\', \'purchasePrices:\') WHERE `slot_config` LIKE \'%purchasePrice:%\'');
        $connection->executeStatement('UPDATE cms_slot_translation  SET `config` = REPLACE(`config`, \'purchasePrice:\', \'purchasePrices:\') WHERE `config` LIKE \'%purchasePrice:%\'');
        $connection->executeStatement('UPDATE product_sorting SET `fields` = REPLACE(`fields`, \'product.purchasePrice",\', \'product.purchasePrices",\') WHERE `fields` LIKE \'%purchasePrice",%\'');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
