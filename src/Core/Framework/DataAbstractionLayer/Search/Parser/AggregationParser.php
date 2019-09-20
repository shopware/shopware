<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Parser;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidAggregationQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidFilterQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class AggregationParser
{
    public function buildAggregations(EntityDefinition $definition, array $payload, Criteria $criteria, SearchRequestException $searchRequestException): void
    {
        if (!\is_array($payload['aggregations'])) {
            throw new InvalidAggregationQueryException('The aggregations parameter has to be a list of aggregations.');
        }

        foreach ($payload['aggregations'] as $index => $aggregation) {
            $parsed = $this->parseAggregation($index, $definition, $aggregation, $searchRequestException);

            if ($parsed) {
                $criteria->addAggregation($parsed);
            }
        }
    }

    private function parseAggregation(int $index, EntityDefinition $definition, array $aggregation, SearchRequestException $exceptions): ?Aggregation
    {
        if (!\is_array($aggregation)) {
            $exceptions->add(new InvalidAggregationQueryException('The field "%s" should be a list of aggregations.'), '/aggregations/' . $index);

            return null;
        }

        $name = array_key_exists('name', $aggregation) ? (string) $aggregation['name'] : null;

        if (empty($name) || is_numeric($name)) {
            $exceptions->add(new InvalidAggregationQueryException('The aggregation name should be a non-empty string.'), '/aggregations/' . $index);

            return null;
        }

        $type = $aggregation['type'] ?? null;

        if (empty($type) || is_numeric($type)) {
            $exceptions->add(new InvalidAggregationQueryException('The aggregations of "%s" should be a non-empty string.'), '/aggregations/' . $index);

            return null;
        }

        if (empty($aggregation['field']) && $type !== 'filter') {
            $exceptions->add(new InvalidAggregationQueryException('The aggregation should contain a "field".'), '/aggregations/' . $index . '/' . $type . '/field');

            return null;
        }

        $field = null;
        if ($type !== 'filter') {
            $field = static::buildFieldName($definition, $aggregation['field']);
        }
        switch ($type) {
            case 'avg':
                return new AvgAggregation($name, $field);
            case 'max':
                return new MaxAggregation($name, $field);
            case 'min':
                return new MinAggregation($name, $field);
            case 'stats':
                return new StatsAggregation($name, $field);
            case 'sum':
                return new SumAggregation($name, $field);
            case 'count':
                return new CountAggregation($name, $field);
            case 'entity':
                if (!isset($aggregation['definition'])) {
                    $exceptions->add(new InvalidAggregationQueryException('The aggregation should contain a "definition".'), '/aggregations/' . $index . '/' . $type . '/field');

                    return null;
                }

                return new EntityAggregation($name, $field, $aggregation['definition']);

            case 'filter':
                if (empty($aggregation['filter'])) {
                    $exceptions->add(new InvalidAggregationQueryException('The aggregation should contain an array of filters in property "filter".'), '/aggregations/' . $index . '/' . $type . '/field');

                    return null;
                }
                if (empty($aggregation['aggregation'])) {
                    $exceptions->add(new InvalidAggregationQueryException('The aggregation should contain an array of filters in property "filter".'), '/aggregations/' . $index . '/' . $type . '/field');

                    return null;
                }
                $filters = [];

                foreach ($aggregation['filter'] as $filterIndex => $query) {
                    try {
                        $filters[] = QueryStringParser::fromArray($definition, $query, $exceptions, '/filter/' . $filterIndex);
                    } catch (InvalidFilterQueryException $ex) {
                        $exceptions->add($ex, $ex->getPath());
                    }
                }

                $nested = $this->parseAggregation($index, $definition, $aggregation['aggregation'], $exceptions);

                return new FilterAggregation($name, $nested, $filters);

            case 'histogram':
                $nested = null;
                $sorting = null;

                if (!isset($aggregation['interval'])) {
                    $exceptions->add(new InvalidAggregationQueryException('The aggregation should contain an date interval.'), '/aggregations/' . $index . '/' . $type . '/interval');

                    return null;
                }

                $interval = $aggregation['interval'];
                $format = $aggregation['format'] ?? null;

                if (isset($aggregation['aggregation'])) {
                    $nested = $this->parseAggregation($index, $definition, $aggregation['aggregation'], $exceptions);
                }
                if (isset($aggregation['sort'])) {
                    $sort = $aggregation['sort'];
                    $order = $sort['order'] ?? FieldSorting::ASCENDING;
                    $naturalSorting = $sort['naturalSorting'] ?? false;

                    if (strcasecmp($order, 'desc') === 0) {
                        $order = FieldSorting::DESCENDING;
                    } else {
                        $order = FieldSorting::ASCENDING;
                    }

                    $sorting = new FieldSorting($sort['field'], $order, (bool) $naturalSorting);
                }

                return new DateHistogramAggregation($name, $field, $interval, $sorting, $nested, $format);

            case 'terms':
                $nested = null;
                $limit = null;
                $sorting = null;

                if (isset($aggregation['aggregation'])) {
                    $nested = $this->parseAggregation($index, $definition, $aggregation['aggregation'], $exceptions);
                }

                if (isset($aggregation['limit'])) {
                    $limit = (int) $aggregation['limit'];
                }
                if (isset($aggregation['sort'])) {
                    $sort = $aggregation['sort'];
                    $order = $sort['order'] ?? FieldSorting::ASCENDING;
                    $naturalSorting = $sort['naturalSorting'] ?? false;

                    if (strcasecmp($order, 'desc') === 0) {
                        $order = FieldSorting::DESCENDING;
                    } else {
                        $order = FieldSorting::ASCENDING;
                    }

                    $sorting = new FieldSorting($sort['field'], $order, (bool) $naturalSorting);
                }

                return new TermsAggregation($name, $field, $limit, $sorting, $nested);

            default:
                $exceptions->add(new InvalidAggregationQueryException(sprintf('The aggregation type "%s" used as key does not exists.', $type)), '/aggregations/' . $index);

                return null;
        }
    }

    private static function buildFieldName(EntityDefinition $definition, string $fieldName): string
    {
        $prefix = $definition->getEntityName() . '.';

        if (strpos($fieldName, $prefix) === false) {
            return $prefix . $fieldName;
        }

        return $fieldName;
    }
}
