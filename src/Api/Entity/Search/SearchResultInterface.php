<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search;

use Shopware\Context\Struct\ShopContext;

interface SearchResultInterface
{
    public function getAggregations(): array;

    public function getTotal(): int;

    public function getCriteria(): Criteria;

    public function getContext(): ShopContext;

    public function getAggregationResult(): ?AggregationResult;

    public function getIdResult(): IdSearchResult;
}
