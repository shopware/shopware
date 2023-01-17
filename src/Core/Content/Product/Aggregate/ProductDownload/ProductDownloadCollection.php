<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDownload;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ProductDownloadEntity>
 */
class ProductDownloadCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_download_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductDownloadEntity::class;
    }
}
