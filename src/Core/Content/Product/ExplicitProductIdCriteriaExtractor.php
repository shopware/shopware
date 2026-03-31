<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
final class ExplicitProductIdCriteriaExtractor
{
    /**
     * @return array<string, string>
     */
    public static function extract(Criteria $criteria): array
    {
        $productIds = [];

        foreach ($criteria->getIds() as $id) {
            $productIds[$id] = $id;
        }

        foreach (array_merge($criteria->getFilters(), $criteria->getPostFilters()) as $filter) {
            foreach (self::extractFromFilter($filter) as $productId) {
                $productIds[$productId] = $productId;
            }
        }

        return $productIds;
    }

    /**
     * @return array<string>
     */
    private static function extractFromFilter(Filter $filter): array
    {
        if ($filter instanceof EqualsFilter && self::isProductIdField($filter->getField()) && \is_string($filter->getValue())) {
            return [$filter->getValue()];
        }

        if ($filter instanceof EqualsAnyFilter && self::isProductIdField($filter->getField())) {
            return array_values(array_filter($filter->getValue(), static fn ($value): bool => \is_string($value)));
        }

        if ($filter instanceof MultiFilter && !$filter instanceof NotFilter) {
            $productIds = [];

            foreach ($filter->getQueries() as $query) {
                array_push($productIds, ...self::extractFromFilter($query));
            }

            return $productIds;
        }

        return [];
    }

    private static function isProductIdField(string $field): bool
    {
        return \in_array($field, ['id', 'product.id'], true);
    }
}
