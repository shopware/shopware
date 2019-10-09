<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\DataAbstractionLayer\Indexing\BreadcrumbIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductCategoryTreeIndexer;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Storefront\Framework\Seo\DataAbstractionLayer\Indexing\SeoUrlIndexer;

class Migration1570684913ScheduleIndexer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1570684913;
    }

    public function update(Connection $connection): void
    {
        $this->registerIndexer($connection, ProductCategoryTreeIndexer::getName());
        $this->registerIndexer($connection, BreadcrumbIndexer::getName());
        $this->registerIndexer($connection, SeoUrlIndexer::getName());
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
