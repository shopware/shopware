<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Parser;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidFilterQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

class QueryStringParser
{
    public static function fromArray(string $definition, array $query, SearchRequestException $exception, string $path = ''): Filter
    {
        if (empty($query['type'])) {
            throw new InvalidFilterQueryException('Value for filter type is required.');
        }

        switch ($query['type']) {
            case 'term':
                if (empty($query['field'])) {
                    throw new InvalidFilterQueryException('Parameter "field" for term filter is missing.', $path . '/field');
                }

                if (!array_key_exists('value', $query) || $query['value'] === '') {
                    throw new InvalidFilterQueryException('Parameter "value" for term filter is missing.', $path . '/value');
                }

                return new EqualsFilter(self::buildFieldName($definition, $query['field']), $query['value']);
            case 'nested':
                $queries = [];
                $operator = MultiFilter::CONNECTION_AND;

                if (isset($query['operator']) && $query['operator'] === MultiFilter::CONNECTION_OR) {
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
            case 'match':
                if (empty($query['field'])) {
                    throw new InvalidFilterQueryException('Parameter "field" for match filter is missing.', $path . '/field');
                }

                if (!isset($query['value']) || $query['value'] === '') {
                    throw new InvalidFilterQueryException('Parameter "value" for match filter is missing.', $path . '/value');
                }

                return new ContainsFilter(self::buildFieldName($definition, $query['field']), $query['value']);
            case 'not':
                return new NotFilter(
                    $query['operator'] ?? 'AND',
                    array_map(function (array $query) use ($path, $exception, $definition) {
                        return self::fromArray($definition, $query, $exception, $path);
                    }, $query['queries'])
                );
            case 'range':
                return new RangeFilter(self::buildFieldName($definition, $query['field']), $query['parameters']);
            case 'terms':
                if (empty($query['field'])) {
                    throw new InvalidFilterQueryException('Parameter "field" for terms filter is missing.', $path . '/field');
                }

                if (empty($query['value'])) {
                    throw new InvalidFilterQueryException('Parameter "value" for terms filter is missing.', $path . '/value');
                }

                $values = $query['value'];
                if (\is_string($values)) {
                    $values = array_filter(explode('|', $values));
                }

                if (!\is_array($values)) {
                    $values = [$values];
                }

                if (empty($values)) {
                    throw new InvalidFilterQueryException('Parameter "value" for terms filter does not contain any value.', $path . '/value');
                }

                return new EqualsAnyFilter(self::buildFieldName($definition, $query['field']), $values);
        }

        throw new InvalidFilterQueryException(sprintf('Unsupported query type: %s', $query['type']), $path . '/type');
    }

    private static function toArray(Filter $query): array
    {
        switch (true) {
            case $query instanceof EqualsFilter:
                return [
                    'type' => 'term',
                    'field' => $query->getField(),
                    'value' => $query->getValue(),
                ];
            case $query instanceof MultiFilter:
                return [
                    'type' => 'nested',
                    'queries' => array_map(function (Filter $nested) {
                        return self::toArray($nested);
                    }, $query->getQueries()),
                    'operator' => $query->getOperator(),
                ];
            case $query instanceof ContainsFilter:
                return [
                    'type' => 'match',
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
            case $query instanceof RangeFilter:
                return [
                    'type' => 'range',
                    'field' => $query->getField(),
                    'parameters' => $query->getParameters(),
                ];
            case $query instanceof EqualsAnyFilter:
                return [
                    'type' => 'term',
                    'field' => $query->getField(),
                    'value' => implode('|', $query->getValue()),
                ];
            default:
                throw new \RuntimeException(sprintf('Unsupported query type %s', \get_class($query)));
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
