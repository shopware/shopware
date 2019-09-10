<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Service;

use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterCollection;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterEntity;
use Shopware\Core\Content\ProductStream\Exception\NoFilterException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class ProductStreamBuilder implements ProductStreamBuilderInterface
{
    /** @var ProductStreamFilterFactoryInterface */
    private $productStreamFilterService;

    /** @var EntityRepositoryInterface */
    private $productStreamRepository;

    public function __construct(
        ProductStreamFilterFactoryInterface $productStreamFilterService,
        EntityRepositoryInterface $productStreamRepository
    ) {
        $this->productStreamFilterService = $productStreamFilterService;
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

    private function buildNested(
        ProductStreamFilterEntity $filter,
        ProductStreamFilterCollection $filterCollection
    ): array {
        $nestedCollection = [];

        $filter = $filterCollection->get($filter->getId());

        $this->ensureQueries($filter, $filterCollection);

        $nestedFilter = [
            'filter' => $this->productStreamFilterService->createFilter($filter),
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
                return $a['position'] < $b['position'] ? -1 : 1;
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
