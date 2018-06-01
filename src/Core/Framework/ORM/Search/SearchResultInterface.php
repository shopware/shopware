<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search;

use Shopware\Framework\Context;
use Shopware\Framework\ORM\Search\Aggregation\AggregationResultCollection;

interface SearchResultInterface
{
    public function getAggregations(): AggregationResultCollection;

    public function getTotal(): int;

    public function getCriteria(): Criteria;

    public function getContext(): Context;

    public function getAggregationResult(): ?AggregatorResult;

    public function getIdResult(): IdSearchResult;
}
