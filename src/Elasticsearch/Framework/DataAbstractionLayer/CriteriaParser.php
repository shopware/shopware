<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\CompositeAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\NestedAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric;
use ONGR\ElasticsearchDSL\Aggregation\Metric\ValueCountAggregation;
use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\Joining\NestedQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\ExistsQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\PrefixQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\WildcardQuery;
use ONGR\ElasticsearchDSL\Sort\FieldSort;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Defaults;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\XOrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;

class CriteriaParser
{
    private EntityDefinitionQueryHelper $helper;

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

        $field = $this->helper->getField($fieldName, $definition, $root, false);
        if ($field instanceof TranslatedField) {
            $ordered = [];
            foreach ($parts as $part) {
                $ordered[] = $part;
            }
            $parts = $ordered;
        }

        if (!$field instanceof PriceField) {
            return implode('.', $parts);
        }

        if (\in_array(end($parts), ['net', 'gross'], true)) {
            $taxState = end($parts);
            array_pop($parts);
        } elseif ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            $taxState = 'gross';
        } else {
            $taxState = 'net';
        }

        $currencyId = $context->getCurrencyId();
        if (Uuid::isValid((string) end($parts))) {
            $currencyId = end($parts);
            array_pop($parts);
        }

        $parts[] = 'c_' . $currencyId;
        $parts[] = $taxState;

        return implode('.', $parts);
    }

    public function parseSorting(FieldSorting $sorting, EntityDefinition $definition, Context $context): FieldSort
    {
        if ($this->isCheapestPriceField($sorting->getField())) {
            return new FieldSort('_script', $sorting->getDirection(), [
                'type' => 'number',
                'script' => [
                    'id' => 'cheapest_price',
                    'params' => $this->getCheapestPriceParameters($context),
                ],
            ]);
        }

        $accessor = $this->buildAccessor($definition, $sorting->getField(), $context);

        return new FieldSort($accessor, $sorting->getDirection());
    }

    public function parseAggregation(Aggregation $aggregation, EntityDefinition $definition, Context $context): ?AbstractAggregation
    {
        $fieldName = $this->buildAccessor($definition, $aggregation->getField(), $context);

        $fields = $aggregation->getFields();

        $path = null;
        if (\count($fields) > 0) {
            $path = $this->getNestedPath($definition, $fields[0]);
        }

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

            case $filter instanceof PrefixFilter:
                return $this->parsePrefixFilter($filter, $definition, $context);

            case $filter instanceof SuffixFilter:
                return $this->parseSuffixFilter($filter, $definition, $context);

            case $filter instanceof RangeFilter:
                return $this->parseRangeFilter($filter, $definition, $context);

            default:
                throw new \RuntimeException(sprintf('Unsupported filter %s', \get_class($filter)));
        }
    }

    protected function parseFilterAggregation(FilterAggregation $aggregation, EntityDefinition $definition, Context $context): Bucketing\FilterAggregation
    {
        $query = new BoolQuery();
        foreach ($aggregation->getFilter() as $filter) {
            $parsed = $this->parseFilter($filter, $definition, $definition->getEntityName(), $context);
            if ($parsed instanceof NestedQuery) {
                $parsed = $parsed->getQuery();
            }
            $query->add($parsed);
        }

        $filter = new Bucketing\FilterAggregation($aggregation->getName(), $query);

        $nested = $aggregation->getAggregation();

        if (!$nested) {
            throw new \RuntimeException(sprintf('Filter aggregation %s contains no nested aggregation.', $aggregation->getName()));
        }

        $filter->addAggregation(
            $this->parseNestedAggregation($nested, $definition, $context)
        );

        return $filter;
    }

    protected function parseTermsAggregation(TermsAggregation $aggregation, string $fieldName, EntityDefinition $definition, Context $context): AbstractAggregation
    {
        if ($aggregation->getSorting() === null) {
            $terms = new Bucketing\TermsAggregation($aggregation->getName(), $fieldName);

            if ($nested = $aggregation->getAggregation()) {
                $terms->addAggregation(
                    $this->parseNestedAggregation($nested, $definition, $context)
                );
            }

            // set default size to 10.000 => max for default configuration
            $terms->addParameter('size', ElasticsearchHelper::MAX_SIZE_VALUE);

            if ($aggregation->getLimit()) {
                $terms->addParameter('size', (string) $aggregation->getLimit());
            }

            return $terms;
        }

        $composite = new CompositeAggregation($aggregation->getName());

        $accessor = $this->buildAccessor($definition, $aggregation->getSorting()->getField(), $context);

        $sorting = new Bucketing\TermsAggregation($aggregation->getName() . '.sorting', $accessor);
        $sorting->addParameter('order', $aggregation->getSorting()->getDirection());
        $composite->addSource($sorting);

        $terms = new Bucketing\TermsAggregation($aggregation->getName() . '.key', $fieldName);
        $composite->addSource($terms);

        if ($nested = $aggregation->getAggregation()) {
            $composite->addAggregation(
                $this->parseNestedAggregation($nested, $definition, $context)
            );
        }

        // set default size to 10.000 => max for default configuration
        $composite->addParameter('size', ElasticsearchHelper::MAX_SIZE_VALUE);

        if ($aggregation->getLimit()) {
            $composite->addParameter('size', (string) $aggregation->getLimit());
        }

        return $composite;
    }

    protected function parseStatsAggregation(StatsAggregation $aggregation, string $fieldName, Context $context): Metric\StatsAggregation
    {
        if ($this->isCheapestPriceField($aggregation->getField())) {
            return new Metric\StatsAggregation($aggregation->getName(), null, [
                'id' => 'cheapest_price',
                'params' => $this->getCheapestPriceParameters($context),
            ]);
        }

        return new Metric\StatsAggregation($aggregation->getName(), $fieldName);
    }

    protected function parseEntityAggregation(EntityAggregation $aggregation, string $fieldName): Bucketing\TermsAggregation
    {
        $bucketingAggregation = new Bucketing\TermsAggregation($aggregation->getName(), $fieldName);

        $bucketingAggregation->addParameter('size', ElasticsearchHelper::MAX_SIZE_VALUE);

        return $bucketingAggregation;
    }

    protected function parseDateHistogramAggregation(DateHistogramAggregation $aggregation, string $fieldName, EntityDefinition $definition, Context $context): CompositeAggregation
    {
        $composite = new CompositeAggregation($aggregation->getName());

        if ($fieldSorting = $aggregation->getSorting()) {
            $accessor = $this->buildAccessor($definition, $fieldSorting->getField(), $context);

            $sorting = new Bucketing\TermsAggregation($aggregation->getName() . '.sorting', $accessor);
            $sorting->addParameter('order', $fieldSorting->getDirection());

            $composite->addSource($sorting);
        }

        $histogram = new Bucketing\DateHistogramAggregation(
            $aggregation->getName() . '.key',
            $fieldName,
            $aggregation->getInterval(),
            'yyyy-MM-dd HH:mm:ss'
        );

        if ($aggregation->getTimeZone()) {
            $histogram->addParameter('time_zone', $aggregation->getTimeZone());
        }

        $composite->addSource($histogram);

        if ($nested = $aggregation->getAggregation()) {
            $composite->addAggregation(
                $this->parseNestedAggregation($nested, $definition, $context)
            );
        }

        return $composite;
    }

    private function getCheapestPriceParameters(Context $context): array
    {
        return [
            'accessors' => $this->getCheapestPriceAccessors($context),
            'decimals' => 10 ** $context->getRounding()->getDecimals(),
            'round' => $this->useCashRounding($context),
            'multiplier' => 100 / ($context->getRounding()->getInterval() * 100),
        ];
    }

    private function useCashRounding(Context $context): bool
    {
        if ($context->getRounding()->getDecimals() !== 2) {
            return false;
        }

        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            return true;
        }

        return $context->getRounding()->roundForNet();
    }

    private function getCheapestPriceAccessors(Context $context): array
    {
        $accessors = [];

        $tax = $context->getTaxState() === CartPrice::TAX_STATE_GROSS ? 'gross' : 'net';

        $ruleIds = array_merge($context->getRuleIds(), ['default']);

        foreach ($ruleIds as $ruleId) {
            $key = implode('_', [
                'cheapest_price',
                'rule' . $ruleId,
                'currency' . $context->getCurrencyId(),
                $tax,
            ]);
            $accessors[] = ['key' => $key, 'factor' => 1];

            if ($context->getCurrencyId() === Defaults::CURRENCY) {
                continue;
            }

            $key = implode('_', [
                'cheapest_price',
                'rule' . $ruleId,
                'currency' . Defaults::CURRENCY,
                $tax,
            ]);

            $accessors[] = ['key' => $key, 'factor' => $context->getCurrencyFactor()];
        }

        return $accessors;
    }

    private function parseNestedAggregation(Aggregation $aggregation, EntityDefinition $definition, Context $context): AbstractAggregation
    {
        $fieldName = $this->buildAccessor($definition, $aggregation->getField(), $context);

        return $this->createAggregation($aggregation, $fieldName, $definition, $context);
    }

    private function createAggregation(Aggregation $aggregation, string $fieldName, EntityDefinition $definition, Context $context): AbstractAggregation
    {
        switch (true) {
            case $aggregation instanceof StatsAggregation:
                return $this->parseStatsAggregation($aggregation, $fieldName, $context);

            case $aggregation instanceof AvgAggregation:
                return new Metric\AvgAggregation($aggregation->getName(), $fieldName);

            case $aggregation instanceof EntityAggregation:
                return $this->parseEntityAggregation($aggregation, $fieldName);

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
                throw new \RuntimeException(sprintf('Provided aggregation of class %s not supported', \get_class($aggregation)));
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

        /** @var string $value */
        $value = $filter->getValue();

        return $this->createNestedQuery(
            new WildcardQuery($accessor, '*' . $value . '*'),
            $definition,
            $filter->getField()
        );
    }

    private function parsePrefixFilter(PrefixFilter $filter, EntityDefinition $definition, Context $context): BuilderInterface
    {
        $accessor = $this->buildAccessor($definition, $filter->getField(), $context);

        $value = $filter->getValue();

        return $this->createNestedQuery(
            new PrefixQuery($accessor, $value),
            $definition,
            $filter->getField()
        );
    }

    private function parseSuffixFilter(SuffixFilter $filter, EntityDefinition $definition, Context $context): BuilderInterface
    {
        $accessor = $this->buildAccessor($definition, $filter->getField(), $context);

        $value = $filter->getValue();

        return $this->createNestedQuery(
            new WildcardQuery($accessor, '*' . $value),
            $definition,
            $filter->getField()
        );
    }

    private function parseRangeFilter(RangeFilter $filter, EntityDefinition $definition, Context $context): BuilderInterface
    {
        if ($this->isCheapestPriceField($filter->getField())) {
            $params = [];
            foreach ($filter->getParameters() as $key => $value) {
                $params[$key] = (float) $value;
            }

            return new ScriptIdQuery('cheapest_price_filter', [
                'params' => array_merge(
                    $params,
                    $this->getCheapestPriceParameters($context)
                ),
            ]);
        }

        $accessor = $this->buildAccessor($definition, $filter->getField(), $context);

        return $this->createNestedQuery(
            new RangeQuery($accessor, $filter->getParameters()),
            $definition,
            $filter->getField()
        );
    }

    private function isCheapestPriceField(string $field): bool
    {
        return \in_array($field, ['product.cheapestPrice', 'cheapestPrice'], true);
    }

    private function parseNotFilter(NotFilter $filter, EntityDefinition $definition, string $root, Context $context): BuilderInterface
    {
        $bool = new BoolQuery();
        if (\count($filter->getQueries()) === 0) {
            return $bool;
        }

        if (\count($filter->getQueries()) === 1) {
            $bool->add(
                $this->parseFilter($filter->getQueries()[0], $definition, $root, $context),
                BoolQuery::MUST_NOT
            );

            return $bool;
        }

        switch ($filter->getOperator()) {
            case MultiFilter::CONNECTION_OR:
                $multiFilter = new OrFilter();

                break;
            case MultiFilter::CONNECTION_XOR:
                $multiFilter = new XOrFilter();

                break;
            default: // AND FILTER
                $multiFilter = new AndFilter();

                break;
        }

        foreach ($filter->getQueries() as $query) {
            $multiFilter->addQuery($query);
        }

        $bool->add(
            $this->parseFilter($multiFilter, $definition, $root, $context),
            BoolQuery::MUST_NOT
        );

        return $bool;
    }

    private function parseMultiFilter(MultiFilter $filter, EntityDefinition $definition, string $root, Context $context): BuilderInterface
    {
        switch ($filter->getOperator()) {
            case MultiFilter::CONNECTION_OR:
                return $this->parseOrMultiFilter($filter, $definition, $root, $context);
            case MultiFilter::CONNECTION_AND:
                return $this->parseAndMultiFilter($filter, $definition, $root, $context);
            case MultiFilter::CONNECTION_XOR:
                return $this->parseXorMultiFilter($filter, $definition, $root, $context);
        }

        throw new \InvalidArgumentException('Operator ' . $filter->getOperator() . ' not allowed');
    }

    private function parseAndMultiFilter(MultiFilter $filter, EntityDefinition $definition, string $root, Context $context): BuilderInterface
    {
        $bool = new BoolQuery();

        foreach ($filter->getQueries() as $nested) {
            $bool->add(
                $this->parseFilter($nested, $definition, $root, $context),
                BoolQuery::MUST
            );
        }

        return $bool;
    }

    private function parseOrMultiFilter(MultiFilter $filter, EntityDefinition $definition, string $root, Context $context): BuilderInterface
    {
        $bool = new BoolQuery();

        foreach ($filter->getQueries() as $nested) {
            $bool->add(
                $this->parseFilter($nested, $definition, $root, $context),
                BoolQuery::SHOULD
            );
        }

        return $bool;
    }

    private function parseXorMultiFilter(MultiFilter $filter, EntityDefinition $definition, string $root, Context $context): BuilderInterface
    {
        $bool = new BoolQuery();

        foreach ($filter->getQueries() as $nested) {
            $xorQuery = new BoolQuery();
            foreach ($filter->getQueries() as $mustNot) {
                if ($nested === $mustNot) {
                    $xorQuery->add($this->parseFilter($nested, $definition, $root, $context), BoolQuery::MUST);

                    continue;
                }

                $xorQuery->add($this->parseFilter($mustNot, $definition, $root, $context), BoolQuery::MUST_NOT);
            }

            $bool->add(
                $xorQuery,
                BoolQuery::SHOULD
            );
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
