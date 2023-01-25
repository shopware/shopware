<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductExportEntity>
 */
#[Package('sales-channel')]
class ProductExportCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_export_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductExportEntity::class;
    }
}
