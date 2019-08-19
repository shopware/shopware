<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Service;

use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

class ProductStreamFilterService implements ProductStreamFilterServiceInterface
{
    public function getFilterType(string $type): string
    {
        $typeClass = null;

        switch ($type) {
            case 'contains':
                $typeClass = ContainsFilter::class;
                break;
            case 'equalsAny':
                $typeClass = EqualsAnyFilter::class;
                break;
            case 'equals':
                $typeClass = EqualsFilter::class;
                break;
            case 'multi':
                $typeClass = MultiFilter::class;
                break;
            case 'not':
                $typeClass = NotFilter::class;
                break;
            case 'range':
                $typeClass = RangeFilter::class;
                break;
        }

        return $typeClass;
    }

    public function createFilter(string $type, ProductStreamFilterEntity $filterEntity): Filter
    {
        switch ($type) {
            case MultiFilter::class:
            case NotFilter::class:
                $queries = [];

                foreach ($filterEntity->getQueries() as $query) {
                    $queries[] = $this->createFilter($this->getFilterType($query->getType()), $query);
                }

                return new MultiFilter($filterEntity->getOperator(), $queries);

            case EqualsAnyFilter::class:
                return new EqualsAnyFilter($filterEntity->getField(), explode('|', $filterEntity->getValue()));

            default:
                return $type::createFrom($filterEntity);
        }
    }
}
