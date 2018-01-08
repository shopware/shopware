<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search;

use Shopware\Context\Struct\TranslationContext;

interface SearchResultInterface
{
    public function getAggregations(): array;

    public function getTotal(): int;

    public function getCriteria(): Criteria;

    public function getContext(): TranslationContext;

    public function getAggregationResult(): ?AggregationResult;

    public function getIdResult(): IdSearchResult;
}
