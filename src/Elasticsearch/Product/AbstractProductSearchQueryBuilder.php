<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Contracts\Service\ResetInterface;

abstract class AbstractProductSearchQueryBuilder implements ResetInterface
{
    abstract public function getDecorated(): AbstractProductSearchQueryBuilder;

    abstract public function buildQuery(Criteria $criteria, Context $context): BoolQuery;

    abstract public function reset(): void;
}
