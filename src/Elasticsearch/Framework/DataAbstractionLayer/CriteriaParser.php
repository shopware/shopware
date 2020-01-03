<?php
declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\CompositeAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\NestedAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric;
use ONGR\ElasticsearchDSL\Aggregation\Metric\ValueCountAggregation;
use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use ONGR\ElasticsearchDSL\Query\Joining\NestedQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\ExistsQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use ONGR\ElasticsearchDSL\Sort\FieldSort;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\DateHistogramAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MinAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation;
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

    public function buildAccessor(EntityDefinition $definition, string $fieldName, Context $context): string
    {
        $root = $definition->getEntityName();

        $parts = explode('.', $fieldName);
        if ($root === $parts[0]) {
            array_shift($parts);
        }

        $field = $this->helper->getField($fieldName, $definition, $root);

        if ($field instanceof TranslatedField) {
            array_pop($parts);
            $parts[] = 'translated';
            $parts[] = $field->getPropertyName();
        }

        if ($field instanceof PriceField) {
            $parts[] = 'gross';
        }

        return implode('.', $parts);
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

        $esAggregation = $this->createAggregation($aggregation, $fieldName, $definition, $context);

        if (!$path) {
            return $esAggregation;
        }

        $nested = new NestedAggregation($aggregation->getName(), $path);
        $nested->addAggregation($esAggregation);

        return $nested;
    }

    public function parseFilter(Filter $filter, EntityDefinition $definition, string $root, Context $context): BuilderInterface
    {
        switch (true) {
            case $filter instanceof NotFilter:
                return $this->parseNotFilter($filter, $definition, $root, $context);

            case $filter instanceof MultiFilter:
                return $this->parseMultiFilter($filter, $definition, $root, $context);

            case $filter instanceof EqualsFilter:
                return $this->parseEqualsFilter($filter, $definition, $context);

            case $filter instanceof EqualsAnyFilter:
                return $this->parseEqualsAnyFilter($filter, $definition, $context);

            case $filter instanceof ContainsFilter:
                return $this->parseContainsFilter($filter, $definition, $context);

            case $filter instanceof RangeFilter:
                return $this->parseRangeFilter($filter, $definition, $context);

            default:
                throw new \RuntimeException(sprintf('Unsupported filter %s', get_class($filter)));
        }
    }

    protected function parseFilterAggregation(FilterAggregation $aggregation, EntityDefinition $definition, Context $context): Bucketing\FilterAggregation
    {
        $query = new BoolQuery();
        foreach ($aggregation->getFilter() as $filter) {
            $query->add(
                $this->parseFilter($filter, $definition, $definition->getEntityName(), $context)
            );
        }

        $filter = new Bucketing\FilterAggregation($aggregation->getName(), $query);

        $filter->addAggregation(
            $this->parseAggregation($aggregation->getAggregation(), $definition, $context)
        );

        return $filter;
    }

    protected function parseTermsAggregation(TermsAggregation $aggregation, string $fieldName, EntityDefinition $definition, Context $context): CompositeAggregation
    {
        $composite = new CompositeAggregation($aggregation->getName());

        if ($aggregation->getSorting()) {
            $accessor = $this->buildAccessor($definition, $aggregation->getSorting()->getField(), $context);

            $sorting = new Bucketing\TermsAggregation($aggregation->getName() . '.sorting', $accessor);
            $sorting->addParameter('order', $aggregation->getSorting()->getDirection());
            $composite->addSource($sorting);
        }

        $terms = new Bucketing\TermsAggregation($aggregation->getName() . '.key', $fieldName);
        $composite->addSource($terms);

        if ($aggregation->getAggregation()) {
            $composite->addAggregation(
                $this->parseAggregation($aggregation->getAggregation(), $definition, $context)
            );
        }

        // set default size to 10.000 => max for default configuration
        $composite->addParameter('size', 10000);

        if ($aggregation->getLimit()) {
            $composite->addParameter('size', (string) $aggregation->getLimit());
        }

        return $composite;
    }

    protected function parseDateHistogramAggregation(DateHistogramAggregation $aggregation, string $fieldName, EntityDefinition $definition, Context $context): CompositeAggregation
    {
        $composite = new CompositeAggregation($aggregation->getName());

        if ($aggregation->getSorting()) {
            $accessor = $this->buildAccessor($definition, $aggregation->getSorting()->getField(), $context);

            $sorting = new Bucketing\TermsAggregation($aggregation->getName() . '.sorting', $accessor);
            $sorting->addParameter('order', $aggregation->getSorting()->getDirection());

            $composite->addSource($sorting);
        }

        $histogram = new Bucketing\DateHistogramAggregation(
            $aggregation->getName() . '.key',
            $fieldName,
            $aggregation->getInterval(),
            'yyyy-MM-dd HH:mm:ss'
        );
        $composite->addSource($histogram);

        if ($aggregation->getAggregation()) {
            $composite->addAggregation(
                $this->parseAggregation($aggregation->getAggregation(), $definition, $context)
            );
        }

        return $composite;
    }

    private function createAggregation(Aggregation $aggregation, string $fieldName, EntityDefinition $definition, Context $context): AbstractAggregation
    {
        switch (true) {
            case $aggregation instanceof StatsAggregation:
                return new Metric\StatsAggregation($aggregation->getName(), $fieldName);

            case $aggregation instanceof AvgAggregation:
                return new Metric\AvgAggregation($aggregation->getName(), $fieldName);

            case $aggregation instanceof EntityAggregation:
                return new Bucketing\TermsAggregation($aggregation->getName(), $fieldName);

            case $aggregation instanceof MaxAggregation:
                return new Metric\MaxAggregation($aggregation->getName(), $fieldName);

            case $aggregation instanceof MinAggregation:
                return new Metric\MinAggregation($aggregation->getName(), $fieldName);

            case $aggregation instanceof SumAggregation:
                return new Metric\SumAggregation($aggregation->getName(), $fieldName);

            case $aggregation instanceof CountAggregation:
                return new ValueCountAggregation($aggregation->getName(), $fieldName);

            case $aggregation instanceof FilterAggregation:
                return $this->parseFilterAggregation($aggregation, $definition, $context);

            case $aggregation instanceof TermsAggregation:
                return $this->parseTermsAggregation($aggregation, $fieldName, $definition, $context);

            case $aggregation instanceof DateHistogramAggregation:
                return $this->parseDateHistogramAggregation($aggregation, $fieldName, $definition, $context);
            default:
                throw new \RuntimeException(sprintf('Provided aggregation of class %s not supported', get_class($aggregation)));
        }
    }

    private function parseEqualsFilter(EqualsFilter $filter, EntityDefinition $definition, Context $context): BuilderInterface
    {
        $fieldName = $this->buildAccessor($definition, $filter->getField(), $context);

        if ($filter->getValue() === null) {
            $query = new BoolQuery();
            $query->add(new ExistsQuery($fieldName), BoolQuery::MUST_NOT);
        } else {
            $query = new TermQuery($fieldName, $filter->getValue());
        }

        return $this->createNestedQuery($query, $definition, $filter->getField());
    }

    private function parseEqualsAnyFilter(EqualsAnyFilter $filter, EntityDefinition $definition, Context $context): BuilderInterface
    {
        $fieldName = $this->buildAccessor($definition, $filter->getField(), $context);

        return $this->createNestedQuery(
            new TermsQuery($fieldName, array_values($filter->getValue())),
            $definition,
            $filter->getField()
        );
    }

    private function parseContainsFilter(ContainsFilter $filter, EntityDefinition $definition, Context $context): BuilderInterface
    {
        $accessor = $this->buildAccessor($definition, $filter->getField(), $context);

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
        $bool = new BoolQuery();
        foreach ($filter->getQueries() as $nested) {
            $bool->add(
                $this->parseFilter($nested, $definition, $root, $context),
                BoolQuery::MUST_NOT
            );
        }

        return $bool;
    }

    private function parseMultiFilter(MultiFilter $filter, EntityDefinition $definition, string $root, Context $context): BuilderInterface
    {
        $bool = new BoolQuery();
        foreach ($filter->getQueries() as $nested) {
            $bool->add(
                $this->parseFilter($nested, $definition, $root, $context),
                BoolQuery::SHOULD
            );
        }

        if ($filter->getOperator() === MultiFilter::CONNECTION_OR) {
            $bool->addParameter('minimum_should_match', '1');
        } else {
            $bool->addParameter('minimum_should_match', (string) count($filter->getQueries()));
        }

        return $bool;
    }

    private function createNestedQuery(BuilderInterface $query, EntityDefinition $definition, string $field): BuilderInterface
    {
        $path = $this->getNestedPath($definition, $field);

        if ($path) {
            return new NestedQuery($path, $query);
        }

        return $query;
    }

    private function getNestedPath(EntityDefinition $definition, string $accessor): ?string
    {
        if (mb_strpos($accessor, $definition->getEntityName() . '.') === false) {
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
}
