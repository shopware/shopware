<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search;

use Shopware\Framework\ORM\Search\Aggregation\AggregationResultCollection;
use Shopware\Application\Context\Struct\ApplicationContext;

interface SearchResultInterface
{
    public function getAggregations(): AggregationResultCollection;

    public function getTotal(): int;

    public function getCriteria(): Criteria;

    public function getContext(): ApplicationContext;

    public function getAggregationResult(): ?AggregatorResult;

    public function getIdResult(): IdSearchResult;
}
