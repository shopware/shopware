<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use OpenSearchDSL\Aggregation\AbstractAggregation;
use OpenSearchDSL\Aggregation\Bucketing;
use OpenSearchDSL\Aggregation\Bucketing\CompositeAggregation;
use OpenSearchDSL\Aggregation\Bucketing\NestedAggregation;
use OpenSearchDSL\Aggregation\Bucketing\ReverseNestedAggregation;
use OpenSearchDSL\Aggregation\Metric;
use OpenSearchDSL\Aggregation\Metric\ValueCountAggregation;
use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\Joining\NestedQuery;
use OpenSearchDSL\Query\TermLevel\ExistsQuery;
use OpenSearchDSL\Query\TermLevel\PrefixQuery;
use OpenSearchDSL\Query\TermLevel\RangeQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use OpenSearchDSL\Query\TermLevel\TermsQuery;
use OpenSearchDSL\Query\TermLevel\WildcardQuery;
use OpenSearchDSL\Sort\FieldSort;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\RangeAggregation;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SingleFieldFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\XOrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\CountSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Elasticsearch\Framework\ElasticsearchDateHistogramAggregation;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Sort\CountSort;

#[Package('core')]
class CriteriaParser
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityDefinitionQueryHelper $helper,
        private readonly CustomFieldService $customFieldService
    ) {
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
            return new FieldSort('_script', $sorting->getDirection(), null, [
                'type' => 'number',
                'script' => [
                    'id' => 'cheapest_price',
                    'params' => $this->getCheapestPriceParameters($context),
                ],
            ]);
        }

        if ($this->isCheapestPriceField($sorting->getField(), true)) {
            return new FieldSort('_script', $sorting->getDirection(), null, [
                'type' => 'number',
                'script' => [
                    'id' => 'cheapest_price_percentage',
                    'params' => ['accessors' => $this->getCheapestPriceAccessors($context, true)],
                ],
            ]);
        }

        $accessor = $this->buildAccessor($definition, $sorting->getField(), $context);

        if ($sorting instanceof CountSorting) {
            return new CountSort($accessor, $sorting->getDirection());
        }

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

        if (!$path || $aggregation instanceof FilterAggregation) {
            return $esAggregation;
        }

        $nested = new NestedAggregation($aggregation->getName(), $path);
        $nested->addAggregation($esAggregation);

        return $nested;
    }

    public function parseFilter(Filter $filter, EntityDefinition $definition, string $root, Context $context): BuilderInterface
    {
        return match (true) {
            $filter instanceof NotFilter => $this->parseNotFilter($filter, $definition, $root, $context),
            $filter instanceof MultiFilter => $this->parseMultiFilter($filter, $definition, $root, $context),
            $filter instanceof EqualsFilter => $this->parseEqualsFilter($filter, $definition, $context),
            $filter instanceof EqualsAnyFilter => $this->parseEqualsAnyFilter($filter, $definition, $context),
            $filter instanceof ContainsFilter => $this->parseContainsFilter($filter, $definition, $context),
            $filter instanceof PrefixFilter => $this->parsePrefixFilter($filter, $definition, $context),
            $filter instanceof SuffixFilter => $this->parseSuffixFilter($filter, $definition, $context),
            $filter instanceof RangeFilter => $this->parseRangeFilter($filter, $definition, $context),
            default => throw new \RuntimeException(sprintf('Unsupported filter %s', $filter::class)),
        };
    }

    protected function parseFilterAggregation(FilterAggregation $aggregation, EntityDefinition $definition, Context $context): AbstractAggregation
    {
        if ($aggregation->getAggregation() === null) {
            throw new \RuntimeException(sprintf('Filter aggregation %s contains no nested aggregation.', $aggregation->getName()));
        }

        $nested = $this->parseAggregation($aggregation->getAggregation(), $definition, $context);
        if ($nested === null) {
            throw new \RuntimeException(sprintf('Nested filter aggregation %s can not be parsed.', $aggregation->getName()));
        }

        // when aggregation inside the filter aggregation points to a nested object (e.g. product.properties.id) we have to add all filters
        // which points to the same association to the same "nesting" level like the nested aggregation for this association
        $path = $nested instanceof NestedAggregation ? $nested->getPath() : null;
        $bool = new BoolQuery();

        $filters = [];
        foreach ($aggregation->getFilter() as $filter) {
            $query = $this->parseFilter($filter, $definition, $definition->getEntityName(), $context);

            if (!$query instanceof NestedQuery) {
                $filters[] = new Bucketing\FilterAggregation($aggregation->getName(), $query);

                continue;
            }

            // same property path as the "real" aggregation
            if ($query->getPath() === $path) {
                $bool->add($query->getQuery());

                continue;
            }

            // query points to a nested document property - we have to define that the filter points to this field
            $parsed = new NestedAggregation($aggregation->getName(), $query->getPath());

            // now we can defined a filter which points to the nested field (remove NestedQuery layer)
            $filter = new Bucketing\FilterAggregation($aggregation->getName(), $query->getQuery());

            // afterwards we reset the nesting to allow following filters to point to another nested property
            $reverse = new ReverseNestedAggregation($aggregation->getName());

            $filter->addAggregation($reverse);

            $parsed->addAggregation($filter);

            $filters[] = $parsed;
        }

        // nested aggregation should have filters - we have to remap the nesting
        $mapped = $nested;
        if (\count($bool->getQueries()) > 0 && $nested instanceof NestedAggregation) {
            $real = $nested->getAggregation($nested->getName());
            if (!$real instanceof AbstractAggregation) {
                throw new \RuntimeException(sprintf('Nested filter aggregation %s can not be parsed.', $aggregation->getName()));
            }

            $filter = new Bucketing\FilterAggregation($aggregation->getName(), $bool);
            $filter->addAggregation($real);

            $mapped = new NestedAggregation($aggregation->getName(), $nested->getPath());
            $mapped->addAggregation($filter);
        }

        // at this point we have to walk over all filters and create one nested filter for it
        $parent = null;
        $root = $mapped;
        foreach ($filters as $filter) {
            if ($parent === null) {
                $parent = $filter;
                $root = $filter;

                continue;
            }

            $parent->addAggregation($filter);

            if (!$filter instanceof NestedAggregation) {
                $parent = $filter;

                continue;
            }

            $filter = $filter->getAggregation($filter->getName());
            if (!$filter instanceof AbstractAggregation) {
                throw new \RuntimeException('Expected nested+filter+reverse pattern for parsed filters to set next parent correctly');
            }

            $parent = $filter->getAggregation($filter->getName());
            if (!$parent instanceof AbstractAggregation) {
                throw new \RuntimeException('Expected nested+filter+reverse pattern for parsed filters to set next parent correctly');
            }
        }

        // it can happen, that $parent is not defined if the "real" aggregation is a nested and all filters points to the same property
        // than we return the following structure:  [nested-agg] + filter-agg + real-agg    ( [] = optional )
        if ($parent === null) {
            return $root;
        }

        // at this point we have some other filters which point to another nested object as the "real" aggregation
        // than we return the following structure:  [nested-agg] + filter-agg + [reverse-nested-agg] + [nested-agg] + real-agg   ( [] = optional )
        $parent->addAggregation($mapped);

        return $root;
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

        if ($this->isCheapestPriceField($aggregation->getField(), true)) {
            return new Metric\StatsAggregation($aggregation->getName(), null, [
                'id' => 'cheapest_price_percentage',
                'params' => ['accessors' => $this->getCheapestPriceAccessors($context, true)],
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

        $histogram = new ElasticsearchDateHistogramAggregation(
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

    protected function parseRangeAggregation(RangeAggregation $aggregation, string $fieldName): Bucketing\RangeAggregation
    {
        return new Bucketing\RangeAggregation(
            $aggregation->getName(),
            $fieldName,
            $aggregation->getRanges()
        );
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

    private function getCheapestPriceAccessors(Context $context, bool $percentage = false): array
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

            if ($percentage) {
                $key .= '_percentage';
            }

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

            if ($percentage) {
                $key .= '_percentage';
            }

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
        return match (true) {
            $aggregation instanceof StatsAggregation => $this->parseStatsAggregation($aggregation, $fieldName, $context),
            $aggregation instanceof AvgAggregation => new Metric\AvgAggregation($aggregation->getName(), $fieldName),
            $aggregation instanceof EntityAggregation => $this->parseEntityAggregation($aggregation, $fieldName),
            $aggregation instanceof MaxAggregation => new Metric\MaxAggregation($aggregation->getName(), $fieldName),
            $aggregation instanceof MinAggregation => new Metric\MinAggregation($aggregation->getName(), $fieldName),
            $aggregation instanceof SumAggregation => new Metric\SumAggregation($aggregation->getName(), $fieldName),
            $aggregation instanceof CountAggregation => new ValueCountAggregation($aggregation->getName(), $fieldName),
            $aggregation instanceof FilterAggregation => $this->parseFilterAggregation($aggregation, $definition, $context),
            $aggregation instanceof TermsAggregation => $this->parseTermsAggregation($aggregation, $fieldName, $definition, $context),
            $aggregation instanceof DateHistogramAggregation => $this->parseDateHistogramAggregation($aggregation, $fieldName, $definition, $context),
            $aggregation instanceof RangeAggregation => $this->parseRangeAggregation($aggregation, $fieldName),
            default => throw new \RuntimeException(sprintf('Provided aggregation of class %s not supported', $aggregation::class)),
        };
    }

    private function parseEqualsFilter(EqualsFilter $filter, EntityDefinition $definition, Context $context): BuilderInterface
    {
        $fieldName = $this->buildAccessor($definition, $filter->getField(), $context);

        if ($filter->getValue() === null) {
            $query = new BoolQuery();
            $query->add(new ExistsQuery($fieldName), BoolQuery::MUST_NOT);

            return $this->createNestedQuery($query, $definition, $filter->getField());
        }

        $value = $this->parseValue($definition, $filter, $filter->getValue());

        $query = new TermQuery($fieldName, $value);

        return $this->createNestedQuery($query, $definition, $filter->getField());
    }

    private function parseEqualsAnyFilter(EqualsAnyFilter $filter, EntityDefinition $definition, Context $context): BuilderInterface
    {
        $fieldName = $this->buildAccessor($definition, $filter->getField(), $context);

        $value = $this->parseValue($definition, $filter, \array_values($filter->getValue()));

        return $this->createNestedQuery(
            new TermsQuery($fieldName, $value),
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
            return new ScriptIdQuery('cheapest_price_filter', [
                'params' => array_merge(
                    $this->getRangeParameters($filter),
                    $this->getCheapestPriceParameters($context)
                ),
            ]);
        }

        if ($this->isCheapestPriceField($filter->getField(), true)) {
            return new ScriptIdQuery('cheapest_price_percentage_filter', [
                'params' => array_merge(
                    $this->getRangeParameters($filter),
                    ['accessors' => $this->getCheapestPriceAccessors($context, true)]
                ),
            ]);
        }

        $accessor = $this->buildAccessor($definition, $filter->getField(), $context);

        return $this->createNestedQuery(
            new RangeQuery($accessor, $this->parseValue($definition, $filter, $filter->getParameters())),
            $definition,
            $filter->getField()
        );
    }

    private function isCheapestPriceField(string $field, bool $percentage = false): bool
    {
        if ($percentage) {
            $haystack = ['product.cheapestPrice.percentage', 'cheapestPrice.percentage'];
        } else {
            $haystack = ['product.cheapestPrice', 'cheapestPrice'];
        }

        return \in_array($field, $haystack, true);
    }

    private function getRangeParameters(RangeFilter $filter): array
    {
        $params = [];
        foreach ($filter->getParameters() as $key => $value) {
            $params[$key] = (float) $value;
        }

        return $params;
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

        $multiFilter = match ($filter->getOperator()) {
            MultiFilter::CONNECTION_OR => new OrFilter(),
            MultiFilter::CONNECTION_XOR => new XOrFilter(),
            default => new AndFilter(),
        };

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
        return match ($filter->getOperator()) {
            MultiFilter::CONNECTION_OR => $this->parseOrMultiFilter($filter, $definition, $root, $context),
            MultiFilter::CONNECTION_AND => $this->parseAndMultiFilter($filter, $definition, $root, $context),
            MultiFilter::CONNECTION_XOR => $this->parseXorMultiFilter($filter, $definition, $root, $context),
            default => throw new \InvalidArgumentException('Operator ' . $filter->getOperator() . ' not allowed'),
        };
    }

    private function parseAndMultiFilter(MultiFilter $filter, EntityDefinition $definition, string $root, Context $context): BuilderInterface
    {
        $grouped = [];
        $bool = new BoolQuery();

        foreach ($filter->getQueries() as $nested) {
            $query = $this->parseFilter($nested, $definition, $root, $context);

            if (!$query instanceof NestedQuery) {
                $bool->add($query, BoolQuery::MUST);

                continue;
            }

            if (!\array_key_exists($query->getPath(), $grouped)) {
                $grouped[$query->getPath()] = new BoolQuery();
                $bool->add(new NestedQuery($query->getPath(), $grouped[$query->getPath()]));
            }

            $grouped[$query->getPath()]->add($query->getQuery());
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

    private function getField(EntityDefinition $definition, string $fieldName): ?Field
    {
        $root = $definition->getEntityName();

        $parts = explode('.', $fieldName);
        if ($root === $parts[0]) {
            array_shift($parts);
        }

        return $this->helper->getField($fieldName, $definition, $root, false);
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

    private function parseValue(EntityDefinition $definition, SingleFieldFilter $filter, mixed $value): mixed
    {
        $field = $this->getField($definition, $filter->getField());

        if ($field instanceof TranslatedField) {
            $field = EntityDefinitionQueryHelper::getTranslatedField($definition, $field);
        }

        if ($field instanceof CustomFields) {
            $accessor = \explode('.', $filter->getField());
            $last = \array_pop($accessor);

            $temp = $this->customFieldService->getCustomField($last);

            $field = $temp ?? $field;
        }

        if ($field instanceof BoolField) {
            return match (true) {
                $value === null => null,
                \is_array($value) => \array_map(fn ($value) => (bool) $value, $value),
                default => (bool) $value,
            };
        }

        if ($field instanceof DateTimeField) {
            return match (true) {
                $value === null => null,
                \is_array($value) => \array_map(fn ($value) => (new \DateTime($value))->format(Defaults::STORAGE_DATE_TIME_FORMAT), $value),
                default => (new \DateTime($value))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            };
        }

        if ($field instanceof FloatField) {
            return match (true) {
                $value === null => null,
                \is_array($value) => \array_map(fn ($value) => (float) $value, $value),
                default => (float) $value,
            };
        }

        if ($field instanceof IntField) {
            return match (true) {
                $value === null => null,
                \is_array($value) => \array_map(fn ($value) => (int) $value, $value),
                default => (int) $value,
            };
        }

        return $value;
    }
}
