<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use OpenSearchDSL\BuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractProductSearchQueryBuilder
{
    abstract public function getDecorated(): AbstractProductSearchQueryBuilder;

    abstract public function build(Criteria $criteria, Context $context): BuilderInterface;
}
