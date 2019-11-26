<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\CrossSelling;

use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\ProductStream\Service\ProductStreamService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CrossSellingLoader
{
    /**
     * @var ProductStreamService
     */
    private $productStreamService;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EntityRepositoryInterface
     */
    private $crossSellingRepository;

    public function __construct(
        EntityRepositoryInterface $crossSellingRepository,
        ProductStreamService $productStreamService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->productStreamService = $productStreamService;
        $this->eventDispatcher = $eventDispatcher;
        $this->crossSellingRepository = $crossSellingRepository;
    }

    public function load(string $productId, SalesChannelContext $context): CrossSellingLoaderResult
    {
        $crossSellings = $this->loadCrossSellingsForProduct($productId, $context->getContext());

        $result = new CrossSellingLoaderResult();

        foreach ($crossSellings as $crossSelling) {
            $result->add($this->loadCrossSellingElement($crossSelling, $context));
        }

        $this->eventDispatcher->dispatch(new CrossSellingLoadedEvent($result, $context));

        return $result;
    }

    private function loadCrossSellingsForProduct(string $productId, Context $context): ProductCrossSellingCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $productId))
            ->addSorting(new FieldSorting('position', FieldSorting::ASCENDING))
            ->addAssociation('productStream.filters.queries');

        /** @var ProductCrossSellingCollection $crossSellings */
        $crossSellings = $this->crossSellingRepository->search($criteria, $context)->getEntities();

        return $crossSellings;
    }

    private function loadCrossSellingElement(ProductCrossSellingEntity $crossSelling, SalesChannelContext $context): CrossSellingElement
    {
        $criteria = new Criteria();
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT)
            ->addSorting($crossSelling->getSorting());

        $searchResult = $this->productStreamService->getProducts(
            $crossSelling->getProductStream(),
            $context,
            $criteria
        );

        /** @var ProductCollection $products */
        $products = $searchResult->getEntities();

        $element = new CrossSellingElement();
        $element->setCrossSelling($crossSelling);
        $element->setProducts($products);

        $element->setTotal($searchResult->getTotal());

        return $element;
    }
}
