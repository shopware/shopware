<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Service;

use Shopware\Core\Content\ImportExportV2\Exception\ImportExportV2Exception;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * Converts the export request filter payload into DAL filters and applies them to the criteria.
 *
 * @internal
 */
#[Package('fundamentals@after-sales')]
class CriteriaFilterBuilder
{
    /**
     * @param list<array<string, mixed>> $filters
     */
    public function apply(Criteria $criteria, array $filters): void
    {
        foreach ($filters as $index => $filterConfig) {
            $criteria->addFilter($this->buildFilter($filterConfig, 'filters[' . $index . ']'));
        }
    }

    /**
     * @param array<string, mixed> $filterConfig
     */
    private function buildFilter(array $filterConfig, string $path): Filter
    {
        $type = $filterConfig['type'] ?? null;
        if (!\is_string($type) || $type === '') {
            throw ImportExportV2Exception::invalidExportFilter($path, 'The filter type is required.');
        }

        return match ($type) {
            'equals' => $this->buildEqualsFilter($filterConfig, $path),
            'equalsAny' => $this->buildEqualsAnyFilter($filterConfig, $path),
            'contains' => $this->buildContainsFilter($filterConfig, $path),
            'prefix' => $this->buildPrefixFilter($filterConfig, $path),
            'suffix' => $this->buildSuffixFilter($filterConfig, $path),
            'range' => $this->buildRangeFilter($filterConfig, $path),
            'multi' => $this->buildMultiFilter($filterConfig, $path),
            'not' => $this->buildNotFilter($filterConfig, $path),
            default => throw ImportExportV2Exception::invalidExportFilter($path, 'Unsupported filter type "' . $type . '".'),
        };
    }

    /**
     * @param array<string, mixed> $filterConfig
     */
    private function buildEqualsFilter(array $filterConfig, string $path): EqualsFilter
    {
        return new EqualsFilter(
            $this->requireField($filterConfig, $path),
            $filterConfig['value'] ?? null
        );
    }

    /**
     * @param array<string, mixed> $filterConfig
     */
    private function buildEqualsAnyFilter(array $filterConfig, string $path): EqualsAnyFilter
    {
        $value = $filterConfig['value'] ?? null;
        if (!\is_array($value)) {
            throw ImportExportV2Exception::invalidExportFilter($path, 'equalsAny filters require an array value.');
        }

        return new EqualsAnyFilter($this->requireField($filterConfig, $path), $value);
    }

    /**
     * @param array<string, mixed> $filterConfig
     */
    private function buildContainsFilter(array $filterConfig, string $path): ContainsFilter
    {
        return new ContainsFilter(
            $this->requireField($filterConfig, $path),
            $this->requireStringValue($filterConfig, $path)
        );
    }

    /**
     * @param array<string, mixed> $filterConfig
     */
    private function buildPrefixFilter(array $filterConfig, string $path): PrefixFilter
    {
        return new PrefixFilter(
            $this->requireField($filterConfig, $path),
            $this->requireStringValue($filterConfig, $path)
        );
    }

    /**
     * @param array<string, mixed> $filterConfig
     */
    private function buildSuffixFilter(array $filterConfig, string $path): SuffixFilter
    {
        return new SuffixFilter(
            $this->requireField($filterConfig, $path),
            $this->requireStringValue($filterConfig, $path)
        );
    }

    /**
     * @param array<string, mixed> $filterConfig
     */
    private function buildRangeFilter(array $filterConfig, string $path): RangeFilter
    {
        $parameters = $filterConfig['parameters'] ?? null;
        if (!\is_array($parameters) || $parameters === []) {
            throw ImportExportV2Exception::invalidExportFilter($path, 'range filters require a non-empty parameters object.');
        }

        return new RangeFilter($this->requireField($filterConfig, $path), $parameters);
    }

    /**
     * @param array<string, mixed> $filterConfig
     */
    private function buildMultiFilter(array $filterConfig, string $path): MultiFilter
    {
        return new MultiFilter(
            $this->requireOperator($filterConfig, $path),
            $this->buildNestedFilters($filterConfig, $path)
        );
    }

    /**
     * @param array<string, mixed> $filterConfig
     */
    private function buildNotFilter(array $filterConfig, string $path): NotFilter
    {
        return new NotFilter(
            $this->requireOperator($filterConfig, $path),
            $this->buildNestedFilters($filterConfig, $path)
        );
    }

    /**
     * @param array<string, mixed> $filterConfig
     *
     * @return list<Filter>
     */
    private function buildNestedFilters(array $filterConfig, string $path): array
    {
        $queries = $filterConfig['queries'] ?? null;
        if (!\is_array($queries) || $queries === []) {
            throw ImportExportV2Exception::invalidExportFilter($path, 'Nested filters require a non-empty queries list.');
        }

        $filters = [];
        foreach ($queries as $index => $query) {
            if (!\is_array($query)) {
                throw ImportExportV2Exception::invalidExportFilter($path . '.queries[' . $index . ']', 'Each nested filter must be an object.');
            }

            $filters[] = $this->buildFilter($query, $path . '.queries[' . $index . ']');
        }

        return $filters;
    }

    /**
     * @param array<string, mixed> $filterConfig
     */
    private function requireField(array $filterConfig, string $path): string
    {
        $field = $filterConfig['field'] ?? null;
        if (!\is_string($field) || $field === '') {
            throw ImportExportV2Exception::invalidExportFilter($path, 'The filter field is required.');
        }

        return $field;
    }

    /**
     * @param array<string, mixed> $filterConfig
     */
    private function requireStringValue(array $filterConfig, string $path): string
    {
        $value = $filterConfig['value'] ?? null;
        if (!\is_string($value) || $value === '') {
            throw ImportExportV2Exception::invalidExportFilter($path, 'The filter value must be a non-empty string.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $filterConfig
     */
    private function requireOperator(array $filterConfig, string $path): string
    {
        $operator = $filterConfig['operator'] ?? null;
        if (!\is_string($operator) || !\in_array($operator, [MultiFilter::CONNECTION_AND, MultiFilter::CONNECTION_OR], true)) {
            throw ImportExportV2Exception::invalidExportFilter($path, 'The filter operator must be "AND" or "OR".');
        }

        return $operator;
    }
}
