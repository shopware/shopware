<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\CrossSelling;

use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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
     * @var ProductStreamBuilder
     */
    private $productStreamBuilder;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

    public function __construct(
        EntityRepositoryInterface $crossSellingRepository,
        EventDispatcherInterface $eventDispatcher,
        ProductStreamBuilder $productStreamBuilder,
        SalesChannelRepositoryInterface $productRepository
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->crossSellingRepository = $crossSellingRepository;
        $this->productStreamBuilder = $productStreamBuilder;
        $this->productRepository = $productRepository;
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
            ->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));

        /** @var ProductCrossSellingCollection $crossSellings */
        $crossSellings = $this->crossSellingRepository->search($criteria, $context)->getEntities();

        return $crossSellings;
    }

    private function loadCrossSellingElement(ProductCrossSellingEntity $crossSelling, SalesChannelContext $context): CrossSellingElement
    {
        $filters = $this->productStreamBuilder->buildFilters(
            $crossSelling->getProductStreamId(),
            $context->getContext()
        );

        $criteria = new Criteria();
        $criteria->addFilter(...$filters)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT)
            ->addSorting($crossSelling->getSorting());

        $searchResult = $this->productRepository->search($criteria, $context);

        /** @var ProductCollection $products */
        $products = $searchResult->getEntities();

        $element = new CrossSellingElement();
        $element->setCrossSelling($crossSelling);
        $element->setProducts($products);

        $element->setTotal($searchResult->getTotal());

        return $element;
    }
}
