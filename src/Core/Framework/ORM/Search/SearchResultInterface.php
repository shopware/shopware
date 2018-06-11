<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Search;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Search\Aggregation\AggregationResultCollection;

interface SearchResultInterface
{
    public function getAggregations(): AggregationResultCollection;

    public function getTotal(): int;

    public function getCriteria(): Criteria;

    public function getContext(): Context;

    public function getAggregationResult(): ?AggregatorResult;

    public function getIdResult(): IdSearchResult;
}
