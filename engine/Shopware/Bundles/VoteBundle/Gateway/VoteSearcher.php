<?php

namespace VoteBundle\Gateway;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Criteria;
use Shopware\Search\Search;
use Shopware\Search\SearchResultInterface;

class VoteSearcher extends Search
{
    protected function createQuery(Criteria $criteria, TranslationContext $context): QueryBuilder
    {

    }


    protected function createResult(array $rows, int $total, TranslationContext $context): SearchResultInterface
    {

    }
}