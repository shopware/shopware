<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Service;

use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterEntity;
use Shopware\Core\Content\ProductStream\Exception\FilterNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

class ProductStreamFilterFactory implements ProductStreamFilterFactoryInterface
{
    public function createFilter(ProductStreamFilterEntity $filterEntity): Filter
    {
        $class = $this->getFilterClass($filterEntity->getType());

        switch ($class) {
            case MultiFilter::class:
            case NotFilter::class:
                $queries = [];

                foreach ($filterEntity->getQueries() as $query) {
                    $queries[] = $this->createFilter($query);
                }

                return new MultiFilter($filterEntity->getOperator(), $queries);

            case EqualsAnyFilter::class:
                return new EqualsAnyFilter($filterEntity->getField(), explode('|', $filterEntity->getValue()));

            default:
                return $class::createFrom($filterEntity);
        }
    }

    private function getFilterClass(string $type): string
    {
        switch ($type) {
            case 'contains':
                return ContainsFilter::class;
            case 'equalsAny':
                return EqualsAnyFilter::class;
            case 'equals':
                return EqualsFilter::class;
            case 'multi':
                return MultiFilter::class;
            case 'not':
                return NotFilter::class;
            case 'range':
                return RangeFilter::class;
            default:
                if (!in_array(Filter::class, class_implements($type), true)) {
                    throw new FilterNotFoundException($type);
                }

                return $type;
        }
    }
}
