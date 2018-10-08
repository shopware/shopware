<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Search\Parser;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Exception\InvalidFilterQueryException;
use Shopware\Core\Framework\ORM\Exception\SearchRequestException;
use Shopware\Core\Framework\ORM\Search\Query\MatchQuery;
use Shopware\Core\Framework\ORM\Search\Query\NestedQuery;
use Shopware\Core\Framework\ORM\Search\Query\NotQuery;
use Shopware\Core\Framework\ORM\Search\Query\Query;
use Shopware\Core\Framework\ORM\Search\Query\RangeQuery;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Search\Query\TermsQuery;

class QueryStringParser
{
    public static function fromArray(string $definition, array $query, SearchRequestException $exception, string $path = ''): Query
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

                return new TermQuery(self::buildFieldName($definition, $query['field']), $query['value']);
            case 'nested':
                $queries = [];
                $operator = NestedQuery::OPERATOR_AND;

                if (isset($query['operator']) && $query['operator'] === NestedQuery::OPERATOR_OR) {
                    $operator = NestedQuery::OPERATOR_OR;
                }

                foreach ($query['queries'] as $index => $subQuery) {
                    try {
                        $queries[] = self::fromArray($definition, $subQuery, $exception, $path . '/queries/' . $index);
                    } catch (InvalidFilterQueryException $ex) {
                        $exception->add($ex, $ex->getPath());
                        continue;
                    }
                }

                return new NestedQuery($queries, $operator);
            case 'match':
                if (empty($query['field'])) {
                    throw new InvalidFilterQueryException('Parameter "field" for match filter is missing.', $path . '/field');
                }

                if (!isset($query['value']) || $query['value'] === '') {
                    throw new InvalidFilterQueryException('Parameter "value" for match filter is missing.', $path . '/value');
                }

                return new MatchQuery(self::buildFieldName($definition, $query['field']), $query['value']);
            case 'not':
                return new NotQuery(
                    array_map(function (array $query) use ($path, $exception, $definition) {
                        return self::fromArray($definition, $query, $exception, $path);
                    }, $query['queries']),
                    array_key_exists('operator', $query) ? $query['operator'] : 'AND'
                );
            case 'range':
                return new RangeQuery(self::buildFieldName($definition, $query['field']), $query['parameters']);
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

                return new TermsQuery(self::buildFieldName($definition, $query['field']), $values);
        }

        throw new InvalidFilterQueryException(sprintf('Unsupported query type: %s', $query['type']), $path . '/type');
    }

    private static function toArray(Query $query): array
    {
        switch (true) {
            case $query instanceof TermQuery:
                return [
                    'type' => 'term',
                    'field' => $query->getField(),
                    'value' => $query->getValue(),
                ];
            case $query instanceof NestedQuery:
                return [
                    'type' => 'nested',
                    'queries' => array_map(function (Query $nested) {
                        return self::toArray($nested);
                    }, $query->getQueries()),
                    'operator' => $query->getOperator(),
                ];
            case $query instanceof MatchQuery:
                return [
                    'type' => 'match',
                    'field' => $query->getField(),
                    'value' => $query->getValue(),
                ];
            case $query instanceof NotQuery:
                return [
                    'type' => 'not',
                    'queries' => array_map(function (Query $nested) {
                        return self::toArray($nested);
                    }, $query->getQueries()),
                    'operator' => $query->getOperator(),
                ];
            case $query instanceof RangeQuery:
                return [
                    'type' => 'range',
                    'field' => $query->getField(),
                    'parameters' => $query->getParameters(),
                ];
            case $query instanceof TermsQuery:
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
