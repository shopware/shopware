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

    private static function isSupportedField(string $field): bool
    {
        return \in_array($field, self::SUPPORTED_FIELDS, true);
    }
}
