<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Parser;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidFilterQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidRangeFilterParamException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NorFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\Clock;

/**
 * @internal
 *
 * @phpstan-type EqualsFilterType array{type: 'equals', field: string, value: mixed}
 * @phpstan-type NotFilterType array{type: 'not', queries: array<mixed>, operator: string}
 * @phpstan-type MultiFilterType array{type: 'multi', queries: array<mixed>, operator: string}
 * @phpstan-type ContainsFilterType array{type: 'contains', field: string, value: mixed}
 * @phpstan-type PrefixFilterType array{type: 'prefix', field: string, value: mixed}
 * @phpstan-type SuffixFilterType array{type: 'suffix', field: string, value: mixed}
 * @phpstan-type RangeFilterType array{type: 'range'|'until'|'since', field: string, value?: mixed, parameters: array<string, mixed>}
 * @phpstan-type EqualsAnyFilterType array{type: 'equalsAny', field: string, value: mixed}
 * @phpstan-type Query array{
 *     type: string,
 *     field?: string,
 *     value?: mixed,
 *     parameters?: array{
 *         operator: RangeFilter::*
 *     },
 *     queries?: list<array{
 *         type: string,
 *         field?: string,
 *         value?: mixed
 *     }>|null
 * }
 */
#[Package('framework')]
class QueryStringParser
{
    /**
     * @param array<string, mixed> $query
     */
    public static function fromArray(EntityDefinition $definition, array $query, SearchRequestException $exception, string $path = ''): Filter
    {
        $queryType = $query['type'] ?? '';
        if (!\is_string($queryType) || $queryType === '') {
            throw DataAbstractionLayerException::invalidFilterQuery('Value for filter type is required.');
        }

        switch ($queryType) {
            case 'equals':
                $queryField = $query['field'] ?? '';
                if (!\is_string($queryField) || $queryField === '') {
                    throw DataAbstractionLayerException::invalidFilterQuery('Parameter "field" for equals filter is missing.', $path . '/field');
                }

                if (!\array_key_exists('value', $query)) {
                    throw DataAbstractionLayerException::invalidFilterQuery('Parameter "value" for equals filter is missing.', $path . '/value');
                }

                if (!\is_scalar($query['value']) && $query['value'] !== null) {
                    throw DataAbstractionLayerException::invalidFilterQuery('Parameter "value" for equals filter must be scalar or null.', $path . '/value');
                }

                return new EqualsFilter(self::buildFieldName($definition, $queryField), $query['value']);
            case 'nand':
                return new NandFilter(
                    self::parseQueries($definition, $path, $exception, $query['queries'] ?? [])
                );
            case 'nor':
                return new NorFilter(
                    self::parseQueries($definition, $path, $exception, $query['queries'] ?? [])
                );
            case 'not':
                return new NotFilter(
                    $query['operator'] ?? 'AND',
                    self::parseQueries($definition, $path, $exception, $query['queries'] ?? [])
                );
            case 'and':
                return new AndFilter(
                    self::parseQueries($definition, $path, $exception, $query['queries'] ?? [])
                );
            case 'or':
                return new OrFilter(
                    self::parseQueries($definition, $path, $exception, $query['queries'] ?? [])
                );
            case 'multi':
                $operator = MultiFilter::CONNECTION_AND;

                if (isset($query['operator']) && mb_strtoupper((string) $query['operator']) === MultiFilter::CONNECTION_OR) {
                    $operator = MultiFilter::CONNECTION_OR;
                }

                $queries = self::parseQueries($definition, $path, $exception, $query['queries'] ?? []);

                return new MultiFilter($operator, $queries);
            case 'contains':
                $queryField = $query['field'] ?? '';
                if (!\is_string($queryField) || $queryField === '') {
                    throw DataAbstractionLayerException::invalidFilterQuery('Parameter "field" for contains filter is missing.', $path . '/field');
                }

                $queryValue = $query['value'] ?? '';
                if (!\is_scalar($queryValue) || $queryValue === '') {
                    throw DataAbstractionLayerException::invalidFilterQuery('Parameter "value" for contains filter is missing.', $path . '/value');
                }

                return new ContainsFilter(self::buildFieldName($definition, $queryField), $queryValue);
            case 'prefix':
                $queryField = $query['field'] ?? '';
                if (!\is_string($queryField) || $queryField === '') {
                    throw DataAbstractionLayerException::invalidFilterQuery('Parameter "field" for prefix filter is missing.', $path . '/field');
                }

                if (!\array_key_exists('value', $query) || (!\is_scalar($query['value']) && $query['value'] !== null) || $query['value'] === '') {
                    throw DataAbstractionLayerException::invalidFilterQuery('Parameter "value" for prefix filter is missing.', $path . '/value');
                }

                return new PrefixFilter(self::buildFieldName($definition, $queryField), $query['value']);
            case 'suffix':
                $queryField = $query['field'] ?? '';
                if (!\is_string($queryField) || $queryField === '') {
                    throw DataAbstractionLayerException::invalidFilterQuery('Parameter "field" for suffix filter is missing.', $path . '/field');
                }

                if (!\array_key_exists('value', $query) || (!\is_scalar($query['value']) && $query['value'] !== null) || $query['value'] === '') {
                    throw DataAbstractionLayerException::invalidFilterQuery('Parameter "value" for suffix filter is missing.', $path . '/value');
                }

                return new SuffixFilter(self::buildFieldName($definition, $queryField), $query['value']);

            case 'range':
                $parameters = $query['parameters'] ?? [];
                if (!\is_array($parameters) || $parameters === []) {
                    throw DataAbstractionLayerException::invalidFilterQuery('Parameter "parameters" for range filter is missing.', $path . '/parameters');
                }

                try {
                    return new RangeFilter(self::buildFieldName($definition, $query['field']), $parameters);
                } catch (InvalidRangeFilterParamException $e) {
                    throw DataAbstractionLayerException::invalidFilterQuery($e->getMessage(), $path . '/parameters');
                }
            case 'until':
            case 'since':
                return self::getFilterByRelativeTime(self::buildFieldName($definition, $query['field']), $queryType, $query, $path);
            case 'equalsAll':
                $queryField = $query['field'] ?? '';
                if (!\is_string($queryField) || $queryField === '') {
                    throw DataAbstractionLayerException::invalidFilterQuery('Parameter "field" for equalsAll filter is missing.', $path . '/field');
                }

                if (!\array_key_exists('value', $query) || (!\is_array($query['value']) && !\is_scalar($query['value']) && $query['value'] !== null)) {
                    throw DataAbstractionLayerException::invalidFilterQuery('Parameter "value" for equalsAll filter is missing.', $path . '/value');
                }

                $values = $query['value'];
                if (\is_string($values)) {
                    $values = array_filter(explode('|', $values));
                }

                if (!\is_array($values)) {
                    $values = [$values];
                }

                if ($values === []) {
                    throw DataAbstractionLayerException::invalidFilterQuery('Parameter "value" for equalsAll filter does not contain any value.', $path . '/value');
                }

                $filters = [];
                foreach ($values as $value) {
                    $filters[] = new AndFilter([new EqualsFilter(self::buildFieldName($definition, $queryField), $value)]);
                }

                return new AndFilter($filters);
            case 'equalsAny':
                $queryField = $query['field'] ?? '';
                if (!\is_string($queryField) || $queryField === '') {
                    throw DataAbstractionLayerException::invalidFilterQuery('Parameter "field" for equalsAny filter is missing.', $path . '/field');
                }

                if (!\array_key_exists('value', $query) || (!\is_array($query['value']) && !\is_scalar($query['value']) && $query['value'] !== null)) {
                    throw DataAbstractionLayerException::invalidFilterQuery('Parameter "value" for equalsAny filter is missing.', $path . '/value');
                }

                $values = $query['value'];
                if (\is_string($values)) {
                    $values = array_filter(explode('|', $values));
                }

                if (!\is_array($values)) {
                    $values = [$values];
                }

                if ($values === []) {
                    throw DataAbstractionLayerException::invalidFilterQuery('Parameter "value" for equalsAny filter does not contain any value.', $path . '/value');
                }

                return new EqualsAnyFilter(self::buildFieldName($definition, $queryField), $values);
        }

        throw DataAbstractionLayerException::invalidFilterQuery(\sprintf('Unsupported filter type: %s', $queryType), $path . '/type');
    }

    /**
     * @return EqualsFilterType|NotFilterType|MultiFilterType|ContainsFilterType|PrefixFilterType|SuffixFilterType|RangeFilterType|EqualsAnyFilterType
     */
    public static function toArray(Filter $query): array
    {
        return match (true) {
            $query instanceof EqualsFilter => [
                'type' => 'equals',
                'field' => $query->getField(),
                'value' => $query->getValue(),
            ],
            $query instanceof NotFilter => [
                'type' => 'not',
                'queries' => array_map(static fn (Filter $nested) => self::toArray($nested), $query->getQueries()),
                'operator' => $query->getOperator(),
            ],
            $query instanceof MultiFilter => [
                'type' => 'multi',
                'queries' => array_map(static fn (Filter $nested) => self::toArray($nested), $query->getQueries()),
                'operator' => $query->getOperator(),
            ],
            $query instanceof ContainsFilter => [
                'type' => 'contains',
                'field' => $query->getField(),
                'value' => $query->getValue(),
            ],
            $query instanceof PrefixFilter => [
                'type' => 'prefix',
                'field' => $query->getField(),
                'value' => $query->getValue(),
            ],
            $query instanceof SuffixFilter => [
                'type' => 'suffix',
                'field' => $query->getField(),
                'value' => $query->getValue(),
            ],
            $query instanceof RangeFilter => [
                'type' => 'range',
                'field' => $query->getField(),
                'parameters' => $query->getParameters(),
            ],
            $query instanceof EqualsAnyFilter => [
                'type' => 'equalsAny',
                'field' => $query->getField(),
                'value' => implode('|', $query->getValue()),
            ],
            default => throw DataAbstractionLayerException::invalidFilterQuery(\sprintf('Unsupported filter type %s', $query::class)),
        };
    }

    /**
     * @param list<Query> $queries
     *
     * @return list<Filter>
     */
    private static function parseQueries(EntityDefinition $definition, string $path, SearchRequestException $exception, array $queries): array
    {
        $parsed = [];

        foreach ($queries as $index => $subQuery) {
            try {
                $parsed[] = self::fromArray($definition, $subQuery, $exception, $path . '/queries/' . $index);
            } catch (InvalidFilterQueryException $ex) {
                $exception->add($ex, $ex->getParameters()['path']);
            }
        }

        return $parsed;
    }

    /**
     * @param array<string, mixed> $query
     */
    private static function getFilterByRelativeTime(string $fieldName, string $type, array $query, string $path): MultiFilter
    {
        if (!isset($query['field']) || !\is_string($query['field']) || $query['field'] === '') {
            throw DataAbstractionLayerException::invalidFilterQuery(\sprintf('Parameter "field" for %s filter is missing.', $type), $path . '/field');
        }

        $queryValue = $query['value'] ?? '';
        if (!\is_string($queryValue) || $queryValue === '') {
            throw DataAbstractionLayerException::invalidFilterQuery(\sprintf('Parameter "value" for %s filter is missing.', $type), $path . '/value');
        }

        $operator = $query['parameters']['operator'] ?? '';
        if (!\is_string($operator) || $operator === '') {
            throw DataAbstractionLayerException::invalidFilterQuery(\sprintf('Parameter "parameter.operator" for %s filter is missing.', $type), $path . '/parameters/operator');
        }
        $operator = mb_strtolower($operator);
        $validOperators = [RangeFilter::LTE, RangeFilter::GTE, RangeFilter::LT, RangeFilter::GT, 'eq', 'neq'];
        if (!\in_array($operator, $validOperators, true)) {
            throw DataAbstractionLayerException::invalidFilterQuery(
                \sprintf('Parameter "parameter.operator" for %s filter must be one of: %s', $type, implode(', ', $validOperators)),
                $path . '/parameters/operator'
            );
        }

        $now = Clock::get()->now();

        try {
            $dateInterval = new \DateInterval($queryValue);
        } catch (\Exception) {
            throw DataAbstractionLayerException::invalidFilterQuery(
                \sprintf('Parameter "value" for %s filter must be a valid date interval, got "%s".', $type, $queryValue),
                $path . '/value'
            );
        }

        if ($type === 'since') {
            $dateInterval->invert = 1;
        }
        $thresholdDate = $now->add($dateInterval);

        // if we're matching for time until, date must be in the future
        // if we're matching for time since, date must be in the past
        if ($type === 'until') {
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

        $primaryFilter = match ($operator) {
            'eq' => new RangeFilter($fieldName, self::getDayRangeParameters($thresholdDate)),
            'neq' => new NotFilter(
                NotFilter::CONNECTION_AND,
                [new RangeFilter($fieldName, self::getDayRangeParameters($thresholdDate))]
            ),
            default => new RangeFilter(
                $fieldName,
                [$operator => $thresholdDate->format(Defaults::STORAGE_DATE_FORMAT)]
            ),
        };

        return new MultiFilter(MultiFilter::CONNECTION_AND, [$primaryFilter, $secondaryFilter]);
    }

    /**
     * @param RangeFilter::*|"eq"|"neq" $operator
     *
     * @return RangeFilter::*|"eq"|"neq"
     */
    private static function negateOperator(string $operator): string
    {
        return match ($operator) {
            RangeFilter::LT => RangeFilter::GT,
            RangeFilter::GT => RangeFilter::LT,
            RangeFilter::LTE => RangeFilter::GTE,
            RangeFilter::GTE => RangeFilter::LTE,
            default => $operator,
        };
    }

    private static function buildFieldName(EntityDefinition $definition, string $fieldName): string
    {
        $prefix = $definition->getEntityName() . '.';
        $normalized = self::normalizeAssociationId($definition, $fieldName);

        return str_starts_with($normalized, $prefix) ? $normalized : $prefix . $normalized;
    }

    /**
     * Turns `manufacturer.id` or `product.properties.group.id` into the FK variant
     * (`manufacturerId`, `product.properties.groupId`) whenever that FK really exists.
     */
    private static function normalizeAssociationId(EntityDefinition $definition, string $fieldName): string
    {
        $parts = explode('.', $fieldName);

        if (\count($parts) < 2 || array_pop($parts) !== 'id') {
            return $fieldName;
        }

        $association = array_pop($parts);
        if ($association === null) {
            return $fieldName;
        }

        $candidate = $parts === []
            ? $association . 'Id'
            : implode('.', $parts) . '.' . $association . 'Id';

        $field = EntityDefinitionQueryHelper::getField($candidate, $definition, $definition->getEntityName());

        return $field instanceof FkField ? $candidate : $fieldName;
    }

    /**
     * @return array{gte: string, lte: string}
     */
    private static function getDayRangeParameters(\DateTimeImmutable $thresholdDate): array
    {
        return [
            RangeFilter::GTE => $thresholdDate->setTime(0, 0, 0)->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            RangeFilter::LTE => $thresholdDate->setTime(23, 59, 59)->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
    }
}
