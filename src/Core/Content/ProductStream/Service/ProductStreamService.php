<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Service;

use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterCollection;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterEntity;
use Shopware\Core\Content\ProductStream\Exception\NoFilterException;
use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductStreamService implements ProductStreamServiceInterface
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ProductStreamFilterServiceInterface
     */
    private $productStreamFilterService;

    /**
     * @var EntityRepositoryInterface
     */
    private $productStreamRepository;

    public function __construct(
        SalesChannelRepositoryInterface $productRepository,
        ProductStreamFilterServiceInterface $productStreamFilterService,
        EntityRepositoryInterface $productStreamRepository
    ) {
        $this->productRepository = $productRepository;
        $this->productStreamFilterService = $productStreamFilterService;
        $this->productStreamRepository = $productStreamRepository;
    }

    public function getProducts(
        ProductStreamEntity $productStream,
        SalesChannelContext $context,
        ?int $offset = null,
        ?int $limit = null,
        int $totalCountMode = Criteria::TOTAL_COUNT_MODE_EXACT
    ): EntitySearchResult {
        return $this->productRepository->search(
            $this->buildCriteria($productStream, $offset, $limit, $totalCountMode),
            $context
        );
    }

    public function getProductsById(
        string $productStreamId,
        SalesChannelContext $context,
        ?int $offset = null,
        ?int $limit = null,
        int $totalCountMode = Criteria::TOTAL_COUNT_MODE_EXACT
    ): EntitySearchResult {
        $criteria = new Criteria([$productStreamId]);
        $criteria->addAssociation('filters.queries');

        $productStream = $this->productStreamRepository->search(
            $criteria,
            $context->getContext()
        )->get($productStreamId);

        return $this->productRepository->search(
            $this->buildCriteria($productStream, $offset, $limit, $totalCountMode),
            $context
        );
    }

    public function buildCriteria(
        ProductStreamEntity $productStream,
        ?int $offset = null,
        ?int $limit = null,
        int $totalCountMode = Criteria::TOTAL_COUNT_MODE_EXACT
    ): Criteria {
        $criteria = new Criteria();
        $criteria
            ->setOffset($offset)
            ->setLimit($limit)
            ->setTotalCountMode($totalCountMode);

        if ($productStream->getFilters() === null || $productStream->getFilters()->count() === 0) {
            throw new NoFilterException($productStream->getId());
        }

        $rootFilter = $this->getRootFilter($productStream->getFilters());
        $nestedCollection = $this->buildNested($rootFilter, $productStream->getFilters());

        foreach ($nestedCollection as $item) {
            /** @var Filter $filter */
            $filter = $item['filter'];

            $criteria->addFilter($filter);
        }

        return $criteria;
    }

    private function buildNested(
        ProductStreamFilterEntity $filterEntity,
        ProductStreamFilterCollection $filterCollection
    ) {
        $nestedCollection = [];

        $filterEntity = $filterCollection->get($filterEntity->getId());
        $filterType = $this->productStreamFilterService->getFilterType($filterEntity->getType());

        $this->ensureQueries($filterEntity, $filterCollection);

        $nestedFilter = [
            'filter' => $this->productStreamFilterService->createFilter($filterType, $filterEntity),
            'position' => $filterEntity->getPosition(),
            'children' => [],
        ];

        foreach ($filterEntity->getQueries() as $query) {
            $nestedFilter['children'][] = $this->buildNested($query, $filterCollection);
        }

        $nestedCollection[] = $nestedFilter;

        usort(
            $nestedCollection,
            function (array $a, array $b) {
                return $a['position'] < $b['position'] ? -1 : 1;
            }
        );

        return $nestedCollection;
    }

    private function getRootFilter(ProductStreamFilterCollection $filterCollection): ProductStreamFilterEntity
    {
        foreach ($filterCollection as $filterEntity) {
            if (empty($filterEntity->getParentId())) {
                return $filterEntity;
            }
        }

        throw new \RuntimeException('Root filter not found');
    }

    private function ensureQueries(
        ProductStreamFilterEntity $filterEntity,
        ProductStreamFilterCollection $filterCollection
    ): void {
        $queries = new ProductStreamFilterCollection();

        foreach ($filterEntity->getQueries() as $query) {
            $queries->add($filterCollection->get($query->getId()));
        }

        $filterEntity->setQueries($queries);
    }
}
