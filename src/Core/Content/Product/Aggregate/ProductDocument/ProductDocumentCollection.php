<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDocument;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductDocumentEntity>
 */
#[Package('inventory')]
class ProductDocumentCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_document_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductDocumentEntity::class;
    }
}
