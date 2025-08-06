<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\TermLevel\RangeQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;

/**
 * @internal - This class is part of the internal API, optimized for read and should not be used directly.
 */
#[Package('inventory')]
class ProductCriteriaParser extends CriteriaParser
{

    public function parseFilter(Filter $filter, EntityDefinition $definition, string $root, Context $context): BuilderInterface
    {
        if (!$definition instanceof ProductDefinition) {
            return parent::parseFilter($filter, $definition, $root, $context);
        }

        if ($filter instanceof ProductAvailableFilter) {
            $query = new BoolQuery();

            $query->add(
                new TermQuery('active', true),
                BoolQuery::MUST
            );

            $query->add(
                new RangeQuery('visibility_' . $filter->getSalesChannelId(), [RangeFilter::GTE => $filter->getVisibility()]),
                BoolQuery::MUST
            );

            return $query;
        }

        if ($filter instanceof EqualsFilter && \str_contains($filter->getField(), 'categoriesRo.id')) {
            return new TermQuery('categoryTree', $filter->getValue());
        }

        return parent::parseFilter($filter, $definition, $root, $context);
    }
}
