<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\CrossSelling;

use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CrossSellingLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EntityRepositoryInterface
     */
    private $crossSellingRepository;

    /**
     * @var ProductStreamBuilderInterface
     */
    private $productStreamBuilder;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        EntityRepositoryInterface $crossSellingRepository,
        EventDispatcherInterface $eventDispatcher,
        ProductStreamBuilderInterface $productStreamBuilder,
        SalesChannelRepositoryInterface $productRepository,
        SystemConfigService $systemConfigService
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->crossSellingRepository = $crossSellingRepository;
        $this->productStreamBuilder = $productStreamBuilder;
        $this->productRepository = $productRepository;
        $this->systemConfigService = $systemConfigService;
    }

    public function load(string $productId, SalesChannelContext $context): CrossSellingLoaderResult
    {
        $crossSellings = $this->loadCrossSellingsForProduct($productId, $context);

        $result = new CrossSellingLoaderResult();

        foreach ($crossSellings as $crossSelling) {
            $element = $this->loadCrossSellingElement($crossSelling, $context);

            if ($element && $element->getTotal() > 0) {
                $result->add($element);
            }
        }

        $this->eventDispatcher->dispatch(new CrossSellingLoadedEvent($result, $context));

        return $result;
    }

    private function loadCrossSellingsForProduct(string $productId, SalesChannelContext $context): ProductCrossSellingCollection
    {
        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('product.id', $productId))
            ->addFilter(new EqualsFilter('active', 1))
            ->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));

        /** @var ProductCrossSellingCollection $crossSellings */
        $crossSellings = $this->crossSellingRepository
            ->search($criteria, $context->getContext())
            ->getEntities();

        return $crossSellings;
    }

    private function loadCrossSellingElement(ProductCrossSellingEntity $crossSelling, SalesChannelContext $context): ?CrossSellingElement
    {
        if ($crossSelling->getType() === 'productStream' && $crossSelling->getProductStreamId() !== null) {
            return $this->loadCrossSellingProductStream($crossSelling, $context);
        }

        return $this->loadCrossSellingProductList($crossSelling, $context);
    }

    private function loadCrossSellingProductStream(ProductCrossSellingEntity $crossSelling, SalesChannelContext $context): CrossSellingElement
    {
        $filters = $this->productStreamBuilder->buildFilters(
            $crossSelling->getProductStreamId(),
            $context->getContext()
        );

        $criteria = new Criteria();
        $criteria->addFilter(...$filters)
            ->setLimit($crossSelling->getLimit())
            ->addSorting($crossSelling->getSorting());

        $criteria = $this->handleAvailableStock($criteria, $context);

        $this->eventDispatcher->dispatch(
            new CrossSellingProductStreamCriteriaEvent($crossSelling, $criteria, $context)
        );

        $searchResult = $this->productRepository->search($criteria, $context);

        /** @var ProductCollection $products */
        $products = $searchResult->getEntities();

        $element = new CrossSellingElement();
        $element->setCrossSelling($crossSelling);
        $element->setProducts($products);

        $element->setTotal($searchResult->getTotal());

        return $element;
    }

    private function loadCrossSellingProductList(ProductCrossSellingEntity $crossSelling, SalesChannelContext $context): ?CrossSellingElement
    {
        $criteria = new Criteria([$crossSelling->getId()]);
        $criteria->addAssociation('assignedProducts');

        /** @var ProductCrossSellingEntity $crossSelling */
        $crossSelling = $this->crossSellingRepository->search($criteria, $context->getContext())->getEntities()->first();

        $crossSelling->getAssignedProducts()->sort(function (
            ProductCrossSellingAssignedProductsEntity $a,
            ProductCrossSellingAssignedProductsEntity $b
        ) {
            return $a->getPosition() <=> $b->getPosition();
        });

        $assignedProductsIds = array_values(array_map(function (ProductCrossSellingAssignedProductsEntity $entity) {
            return $entity->getProductId();
        }, $crossSelling->getAssignedProducts()->getElements()));

        $filter = new ProductAvailableFilter(
            $context->getSalesChannel()->getId(),
            ProductVisibilityDefinition::VISIBILITY_LINK
        );

        $criteria = new Criteria($assignedProductsIds);
        $criteria->addAssociation('visibilities');
        $criteria->addFilter($filter);

        if (!count($assignedProductsIds)) {
            return null;
        }

        $criteria = $this->handleAvailableStock($criteria, $context);

        $this->eventDispatcher->dispatch(
            new CrossSellingProductListCriteriaEvent($crossSelling, $criteria, $context)
        );

        $searchResult = $this->productRepository->search($criteria, $context);

        $products = new ProductCollection();
        foreach ($crossSelling->getAssignedProducts()->getElements() as $element) {
            if ($searchResult->has($element->getProductId())) {
                $products->add($searchResult->get($element->getProductId()));
            }
        }

        $element = new CrossSellingElement();
        $element->setCrossSelling($crossSelling);
        $element->setProducts($products);

        $element->setTotal($crossSelling->getAssignedProducts()->count());

        return $element;
    }

    private function handleAvailableStock(Criteria $criteria, SalesChannelContext $context): Criteria
    {
        $salesChannelId = $context->getSalesChannel()->getId();
        $hide = $this->systemConfigService->get('core.listing.hideCloseoutProductsWhenOutOfStock', $salesChannelId);

        if (!$hide) {
            return $criteria;
        }

        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsFilter('product.isCloseout', true),
                    new EqualsFilter('product.available', false),
                ]
            )
        );

        return $criteria;
    }
}
