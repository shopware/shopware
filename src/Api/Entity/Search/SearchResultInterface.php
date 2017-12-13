<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search;

use Shopware\Context\Struct\TranslationContext;

interface SearchResultInterface
{
    public function getAggregations(): ?AggregationResult;

    public function getTotal(): int;

    public function getCriteria(): Criteria;

    public function getContext(): TranslationContext;

    public function setAggregations($aggregations): void;

    public function setTotal(int $total): void;

    public function setCriteria(Criteria $criteria): void;

    public function setContext(TranslationContext $context): void;
}
