<?php

namespace Shopware\Search;

use Shopware\Context\Struct\TranslationContext;

interface SearcherInterface
{
    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult;

    /**
     * @param Criteria $criteria
     * @param TranslationContext $context
     * @return SearchResultInterface
     */
    public function search(Criteria $criteria, TranslationContext $context);

    /**
     * @param Criteria $criteria
     * @param TranslationContext $context
     * @return UuidSearchResult
     */
    public function searchUuids(Criteria $criteria, TranslationContext $context);
}