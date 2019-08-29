<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Parser;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidAggregationQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MinAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueCountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class AggregationParser
{
    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionInstanceRegistry;

    public function __construct(DefinitionInstanceRegistry $definitionInstanceRegistry)
    {
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
    }

    public function buildAggregations(EntityDefinition $definition, array $payload, Criteria $criteria, SearchRequestException $searchRequestException): void
    {
        if (!\is_array($payload['aggregations'])) {
            throw new InvalidAggregationQueryException('The aggregations parameter has to be a list of aggregations.');
        }

        foreach ($payload['aggregations'] as $index => $aggregation) {
            if (!\is_array($aggregation)) {
                $searchRequestException->add(new InvalidAggregationQueryException('The field "%s" should be a list of aggregations.'), '/aggregations/' . $index);
                continue;
            }

            $name = array_key_exists('name', $aggregation) ? (string) $aggregation['name'] : null;

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

            $groupByFields = $aggregation['groupByFields'] ?? [];

            if (!\is_array($groupByFields)) {
                $searchRequestException->add(new InvalidAggregationQueryException('The "groupByFields" should contain an array of fields.'), '/aggregations/' . $index);
                continue;
            }

            $field = static::buildFieldName($definition, $aggregation['field']);
            switch ($type) {
                case 'avg':
                    $criteria->addAggregation(new AvgAggregation($field, $name, ...array_values($groupByFields)));
                    break;

                case 'value':
                    $criteria->addAggregation(new ValueAggregation($field, $name, ...array_values($groupByFields)));
                    break;

                case 'count':
                    $criteria->addAggregation(new CountAggregation($field, $name, ...array_values($groupByFields)));
                    break;

                case 'max':
                    $criteria->addAggregation(new MaxAggregation($field, $name, ...array_values($groupByFields)));
                    break;

                case 'min':
                    $criteria->addAggregation(new MinAggregation($field, $name, ...array_values($groupByFields)));
                    break;

                case 'stats':
                    $criteria->addAggregation(new StatsAggregation($field, $name, ...array_values($groupByFields)));
                    break;

                case 'sum':
                    $criteria->addAggregation(new SumAggregation($field, $name, ...array_values($groupByFields)));
                    break;

                case 'value_count':
                    $criteria->addAggregation(new ValueCountAggregation($field, $name, ...array_values($groupByFields)));
                    break;
                case 'entity':
                    if (!isset($aggregation['definition'])) {
                        $searchRequestException->add(new InvalidAggregationQueryException('The aggregation should contain a "definition".'), '/aggregations/' . $index . '/' . $type . '/field');
                        break;
                    }

                    $definition = $this->definitionInstanceRegistry->getByEntityName($aggregation['definition']);
                    $criteria->addAggregation(new EntityAggregation($field, $definition->getClass(), $name, ...array_values($groupByFields)));
                    break;
                default:
                    $searchRequestException->add(new InvalidAggregationQueryException(sprintf('The aggregation type "%s" used as key does not exists.', $type)), '/aggregations/' . $index);
            }
        }
    }

    private static function buildFieldName(EntityDefinition  $definition, string $fieldName): string
    {
        $prefix = $definition->getEntityName() . '.';

        if (strpos($fieldName, $prefix) === false) {
            return $prefix . $fieldName;
        }

        return $fieldName;
    }
}
