<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Util;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
final class ExplicitProductIdResolver
{
    /**
     * Product stream and DAL filters may address the product primary key either as plain `id`
     * or as the fully qualified `product.id`.
     *
     * @var list<string>
     */
    private const SUPPORTED_FIELDS = ['id', 'product.id'];

    /**
     * @return list<string>
     */
    public static function fromCriteria(Criteria $criteria): array
    {
        return self::fromFilters($criteria->getFilters());
    }

    /**
     * @param array<array-key, Filter> $filters
     *
     * @return list<string>
     */
    public static function fromFilters(array $filters): array
    {
        return array_values(array_unique(self::collect($filters)));
    }

    /**
     * When explicit product ids are evaluated via `Criteria::setIds()`, the original explicit-id
     * predicates become redundant. This method simplifies the filter tree accordingly so the
     * remaining criteria only represent the additional constraints.
     *
     * @param array<array-key, Filter> $filters
     *
     * @return list<Filter>
     */
    public static function removeExplicitIdFilters(array $filters): array
    {
        $simplified = [];

        foreach ($filters as $filter) {
            $result = self::simplifyFilter($filter);

            if ($result === true || $result === null) {
                continue;
            }

            $simplified[] = $result;
        }

        return $simplified;
    }

    /**
     * @param array<array-key, Filter> $filters
     *
     * @return list<string>
     */
    private static function collect(array $filters): array
    {
        $ids = [];

        foreach ($filters as $filter) {
            if ($filter instanceof NotFilter) {
                continue;
            }

            if ($filter instanceof MultiFilter) {
                array_push($ids, ...self::collect($filter->getQueries()));

                continue;
            }

            if ($filter instanceof EqualsFilter && self::isSupportedField($filter->getField()) && \is_string($filter->getValue())) {
                $ids[] = $filter->getValue();

                continue;
            }

            if (!$filter instanceof EqualsAnyFilter || !self::isSupportedField($filter->getField())) {
                continue;
            }

            foreach ($filter->getValue() as $value) {
                if (\is_string($value)) {
                    $ids[] = $value;
                }
            }
        }

        return $ids;
    }

    private static function simplifyFilter(Filter $filter): Filter|bool|null
    {
        if ($filter instanceof NotFilter) {
            return $filter;
        }

        if (self::isExplicitIdFilter($filter)) {
            return true;
        }

        if (!$filter instanceof MultiFilter) {
            return $filter;
        }

        if ($filter->getOperator() === MultiFilter::CONNECTION_XOR) {
            return $filter;
        }

        $queries = [];
        foreach ($filter->getQueries() as $query) {
            $simplified = self::simplifyFilter($query);

            if ($simplified === true) {
                if ($filter->getOperator() === MultiFilter::CONNECTION_OR) {
                    return true;
                }

                continue;
            }

            if ($simplified instanceof Filter) {
                $queries[] = $simplified;
            }
        }

        if ($queries === []) {
            return true;
        }

        if (\count($queries) === 1) {
            return $queries[0];
        }

        return new MultiFilter($filter->getOperator(), $queries);
    }

    private static function isExplicitIdFilter(Filter $filter): bool
    {
        if ($filter instanceof EqualsFilter) {
            return self::isSupportedField($filter->getField()) && \is_string($filter->getValue());
        }

        return $filter instanceof EqualsAnyFilter && self::isSupportedField($filter->getField());
    }

    private static function isSupportedField(string $field): bool
    {
        return \in_array($field, self::SUPPORTED_FIELDS, true);
    }
}
