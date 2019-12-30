<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\CrossSelling;

use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
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
     * @deprecated tag:v6.3.0
     *
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
     * @deprecated tag:v6.3.0 the crossSellingRepositoryParameter will be removed
     */
    public function __construct(
        EntityRepositoryInterface $crossSellingRepository,
        EventDispatcherInterface $eventDispatcher,
        ProductStreamBuilderInterface $productStreamBuilder,
        SalesChannelRepositoryInterface $productRepository
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->crossSellingRepository = $crossSellingRepository;
        $this->productStreamBuilder = $productStreamBuilder;
        $this->productRepository = $productRepository;
    }

    /**
     * @deprecated tag:v6.3.0 use loadForProduct() instead
     */
    public function load(string $productId, SalesChannelContext $context, ?ProductEntity $product = null): CrossSellingLoaderResult
    {
        if (!$product || !$product->getCrossSellings()) {
            $crossSellings = $this->loadCrossSellingsForProduct($productId, $context);
        } else {
            $crossSellings = $product->getCrossSellings();
        }

        $result = new CrossSellingLoaderResult();

        foreach ($crossSellings as $crossSelling) {
            $result->add($this->loadCrossSellingElement($crossSelling, $context));
        }

        $this->eventDispatcher->dispatch(new CrossSellingLoadedEvent($result, $context));

        return $result;
    }

    public function loadForProduct(ProductEntity $product, SalesChannelContext $context): CrossSellingLoaderResult
    {
        // this call should not break decoration as decorators have to extend this service
        return $this->load($product->getId(), $context, $product);
    }

    private function loadCrossSellingsForProduct(string $productId, SalesChannelContext $context): ProductCrossSellingCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $productId))
            ->getAssociation('crossSellings')
                ->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, $context)->get($productId);

        return $product->getCrossSellings();
    }

    private function loadCrossSellingElement(ProductCrossSellingEntity $crossSelling, SalesChannelContext $context): CrossSellingElement
    {
        $filters = $this->productStreamBuilder->buildFilters(
            $crossSelling->getProductStreamId(),
            $context->getContext()
        );

        $criteria = new Criteria();
        $criteria->addFilter(...$filters)
            ->setLimit($crossSelling->getLimit())
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
