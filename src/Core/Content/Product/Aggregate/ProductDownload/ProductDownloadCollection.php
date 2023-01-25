<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDownload;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductDownloadEntity>
 */
#[Package('inventory')]
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
