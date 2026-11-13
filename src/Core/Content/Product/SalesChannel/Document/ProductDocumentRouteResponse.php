<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Document;

use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<ProductDocumentCollection>>
 */
#[Package('inventory')]
class ProductDocumentRouteResponse extends StoreApiResponse
{
    /**
     * @return EntitySearchResult<ProductDocumentCollection>
     */
    public function getResult(): EntitySearchResult
    {
        return $this->object;
    }

    public function getDocuments(): ProductDocumentCollection
    {
        return $this->object->getEntities();
    }
}
