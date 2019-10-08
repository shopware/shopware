<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Service;

use Shopware\Core\Content\ProductExport\Exception\MissingRootFilterException;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterCollection;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterEntity;
use Shopware\Core\Content\ProductStream\Exception\FilterNotFoundException;
use Shopware\Core\Content\ProductStream\Exception\NoFilterException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

class ProductStreamBuilder implements ProductStreamBuilderInterface
{
    /** @var EntityRepositoryInterface */
    private $productStreamRepository;

    public function __construct(
        EntityRepositoryInterface $productStreamRepository
    ) {
        $this->productStreamRepository = $productStreamRepository;
    }

    public function buildFilters(
        string $productStreamId,
        Context $context
    ): array {
        $criteria = new Criteria([$productStreamId]);
        $criteria->addAssociation('filters.queries');

        $productStream = $this->productStreamRepository->search(
            $criteria,
            $context
        )->get($productStreamId);

        if ($productStream->getFilters() === null || $productStream->getFilters()->count() === 0) {
            throw new NoFilterException($productStream->getId());
        }

        $rootFilter = $this->getRootFilter($productStream->getFilters());

        return $this->buildNested($rootFilter, $productStream->getFilters());
    }

    private function createFilter(ProductStreamFilterEntity $filterEntity): Filter
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

    private function buildNested(
        ProductStreamFilterEntity $filter,
        ProductStreamFilterCollection $filterCollection
    ): array {
        $nestedCollection = [];

        $filter = $filterCollection->get($filter->getId());

        $this->ensureQueries($filter, $filterCollection);

        $nestedFilter = [
            'filter' => $this->createFilter($filter),
            'position' => $filter->getPosition(),
            'children' => [],
        ];

        foreach ($filter->getQueries() as $query) {
            $nestedFilter['children'][] = $this->buildNested($query, $filterCollection);
        }

        $nestedCollection[] = $nestedFilter;

        usort(
            $nestedCollection,
            function (array $a, array $b) {
                return $a['position'] <=> $b['position'];
            }
        );

        return array_column($nestedCollection, 'filter');
    }

    private function getRootFilter(ProductStreamFilterCollection $filterCollection): ProductStreamFilterEntity
    {
        foreach ($filterCollection as $filter) {
            if ($filter->getParentId() === null) {
                return $filter;
            }
        }

        throw new MissingRootFilterException();
    }

    private function ensureQueries(
        ProductStreamFilterEntity $filter,
        ProductStreamFilterCollection $filterCollection
    ): void {
        $queries = new ProductStreamFilterCollection();

        foreach ($filter->getQueries() as $query) {
            $queries->add($filterCollection->get($query->getId()));
        }

        $filter->setQueries($queries);
    }
}
