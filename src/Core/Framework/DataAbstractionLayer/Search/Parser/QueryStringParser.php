<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Parser;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidFilterQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;

class QueryStringParser
{
    public static function fromArray(EntityDefinition $definition, array $query, SearchRequestException $exception, string $path = ''): Filter
    {
        if (empty($query['type'])) {
            throw new InvalidFilterQueryException('Value for filter type is required.');
        }

        switch ($query['type']) {
            case 'equals':
                if (empty($query['field'])) {
                    throw new InvalidFilterQueryException('Parameter "field" for equals filter is missing.', $path . '/field');
                }

                if (!\array_key_exists('value', $query) || $query['value'] === '') {
                    throw new InvalidFilterQueryException('Parameter "value" for equals filter is missing.', $path . '/value');
                }

                return new EqualsFilter(self::buildFieldName($definition, $query['field']), $query['value']);
            case 'multi':
                $queries = [];
                $operator = MultiFilter::CONNECTION_AND;

                if (isset($query['operator']) && mb_strtoupper($query['operator']) === MultiFilter::CONNECTION_OR) {
                    $operator = MultiFilter::CONNECTION_OR;
                }

                foreach ($query['queries'] as $index => $subQuery) {
                    try {
                        $queries[] = self::fromArray($definition, $subQuery, $exception, $path . '/queries/' . $index);
                    } catch (InvalidFilterQueryException $ex) {
                        $exception->add($ex, $ex->getPath());

                        continue;
                    }
                }

                return new MultiFilter($operator, $queries);
            case 'contains':
                if (empty($query['field'])) {
                    throw new InvalidFilterQueryException('Parameter "field" for contains filter is missing.', $path . '/field');
                }

                if (!isset($query['value']) || $query['value'] === '') {
                    throw new InvalidFilterQueryException('Parameter "value" for contains filter is missing.', $path . '/value');
                }

                return new ContainsFilter(self::buildFieldName($definition, $query['field']), $query['value']);
            case 'prefix':
                if (empty($query['field'])) {
                    throw new InvalidFilterQueryException('Parameter "field" for prefix filter is missing.', $path . '/field');
                }

                if (!isset($query['value']) || $query['value'] === '') {
                    throw new InvalidFilterQueryException('Parameter "value" for prefix filter is missing.', $path . '/value');
                }

                return new PrefixFilter(self::buildFieldName($definition, $query['field']), $query['value']);
            case 'suffix':
                if (empty($query['field'])) {
                    throw new InvalidFilterQueryException('Parameter "field" for suffix filter is missing.', $path . '/field');
                }

                if (!isset($query['value']) || $query['value'] === '') {
                    throw new InvalidFilterQueryException('Parameter "value" for suffix filter is missing.', $path . '/value');
                }

                return new SuffixFilter(self::buildFieldName($definition, $query['field']), $query['value']);
            case 'not':
                return new NotFilter(
                    $query['operator'] ?? 'AND',
                    array_map(function (array $query) use ($path, $exception, $definition) {
                        return self::fromArray($definition, $query, $exception, $path);
                    }, $query['queries'])
                );
            case 'range':
                return new RangeFilter(self::buildFieldName($definition, $query['field']), $query['parameters']);
            case 'until':
            case 'since':
                return self::getFilterByRelativeTime(self::buildFieldName($definition, $query['field']), $query, $path);
            case 'equalsAny':
                if (empty($query['field'])) {
                    throw new InvalidFilterQueryException('Parameter "field" for equalsAny filter is missing.', $path . '/field');
                }

                if (empty($query['value'])) {
                    throw new InvalidFilterQueryException('Parameter "value" for equalsAny filter is missing.', $path . '/value');
                }

                $values = $query['value'];
                if (\is_string($values)) {
                    $values = array_filter(explode('|', $values));
                }

                if (!\is_array($values)) {
                    $values = [$values];
                }

                if (empty($values)) {
                    throw new InvalidFilterQueryException('Parameter "value" for equalsAny filter does not contain any value.', $path . '/value');
                }

                return new EqualsAnyFilter(self::buildFieldName($definition, $query['field']), $values);
        }

        throw new InvalidFilterQueryException(sprintf('Unsupported filter type: %s', $query['type']), $path . '/type');
    }

    public static function toArray(Filter $query): array
    {
        switch (true) {
            case $query instanceof EqualsFilter:
                return [
                    'type' => 'equals',
                    'field' => $query->getField(),
                    'value' => $query->getValue(),
                ];
            case $query instanceof NotFilter:
                return [
                    'type' => 'not',
                    'queries' => array_map(function (Filter $nested) {
                        return self::toArray($nested);
                    }, $query->getQueries()),
                    'operator' => $query->getOperator(),
                ];
            case $query instanceof MultiFilter:
                return [
                    'type' => 'multi',
                    'queries' => array_map(function (Filter $nested) {
                        return self::toArray($nested);
                    }, $query->getQueries()),
                    'operator' => $query->getOperator(),
                ];
            case $query instanceof ContainsFilter:
                return [
                    'type' => 'contains',
                    'field' => $query->getField(),
                    'value' => $query->getValue(),
                ];
            case $query instanceof PrefixFilter:
                return [
                    'type' => 'prefix',
                    'field' => $query->getField(),
                    'value' => $query->getValue(),
                ];
            case $query instanceof SuffixFilter:
                return [
                    'type' => 'suffix',
                    'field' => $query->getField(),
                    'value' => $query->getValue(),
                ];
            case $query instanceof RangeFilter:
                return [
                    'type' => 'range',
                    'field' => $query->getField(),
                    'parameters' => $query->getParameters(),
                ];
            case $query instanceof EqualsAnyFilter:
                return [
                    'type' => 'equalsAny',
                    'field' => $query->getField(),
                    'value' => implode('|', $query->getValue()),
                ];
            default:
                throw new \RuntimeException(sprintf('Unsupported filter type %s', \get_class($query)));
        }
    }

    private static function getFilterByRelativeTime(string $fieldName, array $query, string $path): MultiFilter
    {
        if (empty($query['field'])) {
            throw new InvalidFilterQueryException(
                sprintf('Parameter "field" for %s filter is missing.', $query['type']),
                $path . '/field'
            );
        }

        if (empty($query['value'])) {
            throw new InvalidFilterQueryException(
                sprintf('Parameter "value" for %s filter is missing.', $query['type']),
                $path . '/value'
            );
        }

        if (empty($query['parameters']['operator'])) {
            throw new InvalidFilterQueryException(
                sprintf('Parameter "parameter.operator" for %s filter is missing.', $query['type']),
                $path . '/parameter'
            );
        }

        $now = new \DateTimeImmutable();
        $dateInterval = new \DateInterval($query['value']);
        if ($query['type'] === 'since') {
            $dateInterval->invert = 1;
        }
        $thresholdDate = $now->add($dateInterval);
        $operator = $query['parameters']['operator'];

        // if we're matching for time until, date must be in the future
        // if we're matching for time since, date must be in the past
        if ($query['type'] === 'until') {
            $secondaryFilter = new RangeFilter(
                $fieldName,
                [RangeFilter::GT => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
            );
        } else {
            $secondaryFilter = new RangeFilter(
                $fieldName,
                [RangeFilter::LT => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
            );
            // for time since we may need to negate the primary filter operator
            $operator = self::negateOperator($operator);
        }

        switch ($operator) {
            case 'eq':
                $primaryFilter = new RangeFilter($fieldName, self::getDayRangeParameters($thresholdDate));

                break;
            case 'neq':
                $primaryFilter = new NotFilter(
                    NotFilter::CONNECTION_AND,
                    [new RangeFilter($fieldName, self::getDayRangeParameters($thresholdDate))]
                );

                break;
            default:
                $primaryFilter = new RangeFilter(
                    $fieldName,
                    [$operator => $thresholdDate->format(Defaults::STORAGE_DATE_FORMAT)]
                );
        }

        return new MultiFilter(MultiFilter::CONNECTION_AND, [$primaryFilter, $secondaryFilter]);
    }

    private static function negateOperator(string $operator): string
    {
        switch ($operator) {
            case RangeFilter::LT:
                return RangeFilter::GT;
            case RangeFilter::GT:
                return RangeFilter::LT;
            case RangeFilter::LTE:
                return RangeFilter::GTE;
            case RangeFilter::GTE:
                return RangeFilter::LTE;
            default:
                return $operator;
        }
    }

    private static function buildFieldName(EntityDefinition $definition, string $fieldName): string
    {
        $prefix = $definition->getEntityName() . '.';

        if (mb_strpos($fieldName, $prefix) === false) {
            return $prefix . $fieldName;
        }

        return $fieldName;
    }

    private static function getDayRangeParameters(\DateTimeImmutable $thresholdDate): array
    {
        return [
            RangeFilter::GTE => $thresholdDate->setTime(0, 0, 0)->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            RangeFilter::LTE => $thresholdDate->setTime(23, 59, 59)->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
    }
}
