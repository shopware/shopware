<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Service;

use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface ProductStreamServiceInterface
{
    public function getProducts(
        ProductStreamEntity $productStream,
        SalesChannelContext $context,
        ?int $offset = null,
        ?int $limit = null,
        int $totalCountMode = Criteria::TOTAL_COUNT_MODE_EXACT
    ): EntitySearchResult;

    public function getProductsById(
        string $productStreamId,
        SalesChannelContext $context,
        ?int $offset = null,
        ?int $limit = null,
        int $totalCountMode = Criteria::TOTAL_COUNT_MODE_EXACT
    ): EntitySearchResult;

    public function buildCriteria(
        ProductStreamEntity $productStream,
        ?int $offset = null,
        ?int $limit = null,
        int $totalCountMode = Criteria::TOTAL_COUNT_MODE_EXACT
    ): Criteria;
}
