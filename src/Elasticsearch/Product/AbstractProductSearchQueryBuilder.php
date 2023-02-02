<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

abstract class AbstractProductSearchQueryBuilder
{
    abstract public function getDecorated(): AbstractProductSearchQueryBuilder;

    abstract public function build(Criteria $criteria, Context $context): BoolQuery;
}
