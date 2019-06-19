<?php
declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\NestedAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric;
use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use ONGR\ElasticsearchDSL\Query\Joining\NestedQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel;
use ONGR\ElasticsearchDSL\Query\TermLevel\ExistsQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use ONGR\ElasticsearchDSL\Sort\FieldSort;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MinAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueCountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class CriteriaParser
{
    /**
     * @var EntityDefinitionQueryHelper
     */
    private $helper;

    public function __construct(EntityDefinitionQueryHelper $helper)
    {
        $this->helper = $helper;
    }

    public function parseSorting(FieldSorting $sorting, EntityDefinition $definition, Context $context): FieldSort
    {
        $accessor = $this->buildAccessor($definition, $sorting->getField(), $context);

        return new FieldSort($accessor, $sorting->getDirection());
    }

    public function parseAggregation(Aggregation $aggregation, EntityDefinition $definition, Context $context): ?AbstractAggregation
    {
        $fieldName = $this->buildAccessor($definition, $aggregation->getField(), $context);

        $path = $this->getNestedPath($definition, $aggregation->getField());

        $esAggregation = $this->createAggregation($aggregation, $fieldName);

        if (!$esAggregation) {
            return $esAggregation;
        }

        if (!$path) {
            return $esAggregation;
        }

        $nested = new NestedAggregation($aggregation->getName(), $path);
        $nested->addAggregation($esAggregation);

        return $nested;
    }

    public function parse(Filter $query, EntityDefinition $definition, string $root, Context $context): BuilderInterface
    {
        switch (true) {
            case $query instanceof NotFilter:
                return $this->parseNotFilter($query, $definition, $root, $context);

            case $query instanceof MultiFilter:
                return $this->parseMultiFilter($query, $definition, $root, $context);

            case $query instanceof EqualsFilter:
                return $this->parseEqualsFilter($query, $definition, $context);

            case $query instanceof EqualsAnyFilter:
                return $this->parseEqualsAnyFilter($query, $definition, $context);

            case $query instanceof ContainsFilter:
                return $this->parseContainsFilter($query, $definition);

            case $query instanceof RangeFilter:
                return $this->parseRangeFilter($query, $definition, $context);

            default:
                throw new \RuntimeException(sprintf('Unsupported filter %s', get_class($query)));
        }
    }

    protected function createAggregation(Aggregation $aggregation, string $fieldName): ?AbstractAggregation
    {
        switch (true) {
            case $aggregation instanceof StatsAggregation:
                return new Metric\StatsAggregation($aggregation->getName(), $fieldName);

            case $aggregation instanceof AvgAggregation:
                return new Metric\AvgAggregation($aggregation->getName(), $fieldName);

            case $aggregation instanceof CountAggregation:
                return new Metric\ValueCountAggregation($aggregation->getName(), $fieldName);

            case $aggregation instanceof EntityAggregation:
                return new TermsAggregation($aggregation->getName(), $fieldName);

            case $aggregation instanceof MaxAggregation:
                return new Metric\MaxAggregation($aggregation->getName(), $fieldName);

            case $aggregation instanceof MinAggregation:
                return new Metric\MinAggregation($aggregation->getName(), $fieldName);

            case $aggregation instanceof SumAggregation:
                return new Metric\SumAggregation($aggregation->getName(), $fieldName);

            case $aggregation instanceof ValueAggregation:
                return new TermsAggregation($aggregation->getName(), $fieldName);

            case $aggregation instanceof ValueCountAggregation:
                return new TermsAggregation($aggregation->getName(), $fieldName);

            default:
                return null;
        }
    }

    private function parseEqualsFilter(EqualsFilter $filter, EntityDefinition $definition, Context $context): BuilderInterface
    {
        $fieldName = $this->buildAccessor($definition, $filter->getField(), $context);

        if ($filter->getValue() === null) {
            $query = new BoolQuery();
            $query->add(new ExistsQuery($fieldName), BoolQuery::MUST_NOT);
        } else {
            $query = new TermLevel\TermQuery($fieldName, $filter->getValue());
        }

        return $this->createNestedQuery($query, $definition, $filter->getField());
    }

    private function parseEqualsAnyFilter(EqualsAnyFilter $filter, EntityDefinition $definition, Context $context): BuilderInterface
    {
        $fieldName = $this->buildAccessor($definition, $filter->getField(), $context);

        return $this->createNestedQuery(
            new TermsQuery($fieldName, $filter->getValue()),
            $definition,
            $filter->getField()
        );
    }

    private function parseContainsFilter(ContainsFilter $filter, EntityDefinition $definition): BuilderInterface
    {
        $accessor = $this->stripRoot($definition, $filter->getField());

        return $this->createNestedQuery(
            new MatchQuery($accessor, $filter->getValue()),
            $definition,
            $filter->getField()
        );
    }

    private function parseRangeFilter(RangeFilter $filter, EntityDefinition $definition, Context $context): BuilderInterface
    {
        $accessor = $this->buildAccessor($definition, $filter->getField(), $context);

        return $this->createNestedQuery(
            new RangeQuery($accessor, $filter->getParameters()),
            $definition,
            $filter->getField()
        );
    }

    private function parseNotFilter(NotFilter $filter, EntityDefinition $definition, string $root, Context $context): BuilderInterface
    {
        $nested = $this->parseMultiFilter($filter, $definition, $root, $context);

        $not = new BoolQuery();
        $not->add($nested, BoolQuery::MUST_NOT);

        return $not;
    }

    private function parseMultiFilter(MultiFilter $filter, EntityDefinition $definition, string $root, Context $context): BuilderInterface
    {
        $bool = new BoolQuery();
        foreach ($filter->getQueries() as $nested) {
            $bool->add(
                $this->parse($nested, $definition, $root, $context),
                BoolQuery::MUST
            );
        }

        return $bool;
    }

    private function createNestedQuery(BuilderInterface $query, EntityDefinition $definition, string $field)
    {
        $path = $this->getNestedPath($definition, $field);

        if ($path) {
            return new NestedQuery($path, $query);
        }

        return $query;
    }

    private function getNestedPath(EntityDefinition $definition, string $accessor)
    {
        if (strpos($accessor, $definition->getEntityName() . '.') === false) {
            $accessor = $definition->getEntityName() . '.' . $accessor;
        }

        $fields = EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $accessor);

        $path = [];
        foreach ($fields as $field) {
            if (!$field instanceof AssociationField) {
                break;
            }

            $path[] = $field->getPropertyName();
        }

        if (empty($path)) {
            return null;
        }

        return implode('.', $path);
    }

    private function buildAccessor(EntityDefinition $definition, string $fieldName, Context $context)
    {
        $root = $definition->getEntityName();

        $accessor = $fieldName;
        if (strpos($fieldName, $root . '.') !== false) {
            $accessor = str_replace($root . '.', '', $fieldName);
        }

        $field = $this->helper->getField($fieldName, $definition, $root);

        if ($field instanceof PriceField) {
            $accessor .= '.gross';
        }

        return $accessor;
    }

    private function stripRoot(EntityDefinition $definition, string $fieldName)
    {
        $root = $definition->getEntityName();

        $accessor = $fieldName;
        if (strpos($fieldName, $root . '.') !== false) {
            $accessor = str_replace($root . '.', '', $fieldName);
        }

        return $accessor;
    }
}
