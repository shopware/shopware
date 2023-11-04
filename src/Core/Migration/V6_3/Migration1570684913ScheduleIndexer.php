<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1570684913ScheduleIndexer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1570684913;
    }

    public function update(Connection $connection): void
    {
        $this->registerIndexer($connection, 'Swag.BreadcrumbIndexer');
        $this->registerIndexer($connection, 'Swag.ChildCountIndexer');
        $this->registerIndexer($connection, 'Swag.EntityIndexer');
        $this->registerIndexer($connection, 'Swag.InheritanceIndexer');
        $this->registerIndexer($connection, 'Swag.ManyToManyIdFieldIndexer');
        $this->registerIndexer($connection, 'Swag.MediaFolderConfigIndexer');
        $this->registerIndexer($connection, 'Swag.MediaFolderSizeIndexer');
        $this->registerIndexer($connection, 'Swag.MediaThumbnailIndexer');
        $this->registerIndexer($connection, 'Swag.ProductCategoryTreeIndexer');
        $this->registerIndexer($connection, 'Swag.ProductKeywordIndexer');
        $this->registerIndexer($connection, 'Swag.ProductListingPriceIndexer');
        $this->registerIndexer($connection, 'Swag.ProductRatingAverageIndexer');
        $this->registerIndexer($connection, 'Swag.ProductSearchKeywordIndexer');
        $this->registerIndexer($connection, 'Swag.ProductStockIndexer');
        $this->registerIndexer($connection, 'Swag.ProductStreamIndexer');
        $this->registerIndexer($connection, 'Swag.PromotionExclusionIndexer');
        $this->registerIndexer($connection, 'Swag.PromotionRedemptionIndexer');
        $this->registerIndexer($connection, 'Swag.RulePayloadIndexer');
        $this->registerIndexer($connection, 'Swag.SeoUrlIndexer');
        $this->registerIndexer($connection, 'Swag.TreeIndexer');
        $this->registerIndexer($connection, 'Swag.VariantListingIndexer');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
