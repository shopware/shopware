<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review;

use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<ProductReviewCollection>>
 */
#[Package('after-sales')]
class ProductReviewRouteResponse extends StoreApiResponse
{
    /**
     * @return EntitySearchResult<ProductReviewCollection>
     */
    public function getResult(): EntitySearchResult
    {
        return $this->object;
    }
}
