<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Promotion\DataAbstractionLayer\Indexing\PromotionExclusionIndexer;
use Shopware\Core\Checkout\Promotion\DataAbstractionLayer\Indexing\PromotionRedemptionIndexer;
use Shopware\Core\Content\Category\DataAbstractionLayer\Indexing\BreadcrumbIndexer;
use Shopware\Core\Content\Media\DataAbstractionLayer\Indexing\MediaFolderConfigIndexer;
use Shopware\Core\Content\Media\DataAbstractionLayer\Indexing\MediaFolderSizeIndexer;
use Shopware\Core\Content\Media\DataAbstractionLayer\Indexing\MediaThumbnailIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductCategoryTreeIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductListingPriceIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductRatingAverageIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductStockIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\VariantListingIndexer;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordIndexer;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\Indexing\ProductStreamIndexer;
use Shopware\Core\Content\Rule\DataAbstractionLayer\Indexing\RulePayloadIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\Indexer\ChildCountIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\Indexer\InheritanceIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\Indexer\ManyToManyIdFieldIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\Indexer\TreeIndexer;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Elasticsearch\Framework\Indexing\EntityIndexer;
use Shopware\Elasticsearch\Product\ProductKeywordIndexer;
use Shopware\Storefront\Framework\Seo\DataAbstractionLayer\Indexing\SeoUrlIndexer;

class Migration1570684913ScheduleIndexer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1570684913;
    }

    public function update(Connection $connection): void
    {
        $this->registerIndexer($connection, BreadcrumbIndexer::getName());
        $this->registerIndexer($connection, ChildCountIndexer::getName());
        $this->registerIndexer($connection, EntityIndexer::getName());
        $this->registerIndexer($connection, InheritanceIndexer::getName());
        $this->registerIndexer($connection, ManyToManyIdFieldIndexer::getName());
        $this->registerIndexer($connection, MediaFolderConfigIndexer::getName());
        $this->registerIndexer($connection, MediaFolderSizeIndexer::getName());
        $this->registerIndexer($connection, MediaThumbnailIndexer::getName());
        $this->registerIndexer($connection, ProductCategoryTreeIndexer::getName());
        $this->registerIndexer($connection, ProductKeywordIndexer::getName());
        $this->registerIndexer($connection, ProductListingPriceIndexer::getName());
        $this->registerIndexer($connection, ProductRatingAverageIndexer::getName());
        $this->registerIndexer($connection, ProductSearchKeywordIndexer::getName());
        $this->registerIndexer($connection, ProductStockIndexer::getName());
        $this->registerIndexer($connection, ProductStreamIndexer::getName());
        $this->registerIndexer($connection, PromotionExclusionIndexer::getName());
        $this->registerIndexer($connection, PromotionRedemptionIndexer::getName());
        $this->registerIndexer($connection, RulePayloadIndexer::getName());
        $this->registerIndexer($connection, SeoUrlIndexer::getName());
        $this->registerIndexer($connection, TreeIndexer::getName());
        $this->registerIndexer($connection, VariantListingIndexer::getName());
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
