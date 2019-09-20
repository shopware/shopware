<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface SalesChannelRepositoryInterface
{
    public function search(Criteria $criteria, SalesChannelContext $salesChannelContext): EntitySearchResult;

    public function aggregate(Criteria $criteria, SalesChannelContext $salesChannelContext): AggregationResultCollection;

    public function searchIds(Criteria $criteria, SalesChannelContext $salesChannelContext): IdSearchResult;
}
