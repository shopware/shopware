<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Parser;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidAggregationQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MinAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueCountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class AggregationParser
{
    public static function buildAggregations(string $definition, array $payload, Criteria $criteria, SearchRequestException $searchRequestException): void
    {
        if (!\is_array($payload['aggregations'])) {
            throw new InvalidAggregationQueryException('The aggregations parameter has to be a list of aggregations.');
        }

        foreach ($payload['aggregations'] as $index => $aggregation) {
            if (!\is_array($aggregation)) {
                $searchRequestException->add(new InvalidAggregationQueryException('The field "%s" should be a list of aggregations.'), '/aggregations/' . $index);
                continue;
            }

            $name = $aggregation['name'] ? (string) $aggregation['name'] : null;

            if (empty($name) || is_numeric($name)) {
                $searchRequestException->add(new InvalidAggregationQueryException('The aggregation name should be a non-empty string.'), '/aggregations/' . $index);
                continue;
            }

            $type = $aggregation['type'] ?? null;

            if (empty($type) || is_numeric($type)) {
                $searchRequestException->add(new InvalidAggregationQueryException('The aggregations of "%s" should be a non-empty string.'), '/aggregations/' . $index);
                continue;
            }

            if (empty($aggregation['field'])) {
                $searchRequestException->add(new InvalidAggregationQueryException('The aggregation should contain a "field".'), '/aggregations/' . $index . '/' . $type . '/field');
                continue;
            }

            $field = static::buildFieldName($definition, $aggregation['field']);
            switch ($type) {
                case 'avg':
                    $criteria->addAggregation(new AvgAggregation($field, $name));
                    break;

                case 'value':
                    $criteria->addAggregation(new ValueAggregation($field, $name));
                    break;

                case 'count':
                    $criteria->addAggregation(new CountAggregation($field, $name));
                    break;

                case 'max':
                    $criteria->addAggregation(new MaxAggregation($field, $name));
                    break;

                case 'min':
                    $criteria->addAggregation(new MinAggregation($field, $name));
                    break;

                case 'stats':
                    $criteria->addAggregation(new StatsAggregation($field, $name));
                    break;

                case 'sum':
                    $criteria->addAggregation(new SumAggregation($field, $name));
                    break;

                case 'value_count':
                    $criteria->addAggregation(new ValueCountAggregation($field, $name));
                    break;

                default:
                    $searchRequestException->add(new InvalidAggregationQueryException(sprintf('The aggregation type "%s" used as key does not exists.', $type)), '/aggregations/' . $index);
            }
        }
    }

    private static function buildFieldName(string $definition, string $fieldName): string
    {
        /** @var EntityDefinition $definition */
        $prefix = $definition::getEntityName() . '.';

        if (strpos($fieldName, $prefix) === false) {
            return $prefix . $fieldName;
        }

        return $fieldName;
    }
}
