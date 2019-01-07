<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Event\ListingEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ListingPageletLoader
{
    /**
     * @var StorefrontProductRepository
     */
    private $productRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var RepositoryInterface
     */
    private $categoryRepository;

    public function __construct(
        StorefrontProductRepository $productRepository,
        RepositoryInterface $categoryRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param ContainerInterface|null $container
     */
    public function setContainer(ContainerInterface $container = null): void
    {
        $category = $this->categoryRepository
            ->read(new ReadCriteria([$request->getNavigationId()]), $context->getContext())
            ->get($request->getNavigationId());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.active', true));

        /** @var CategoryEntity $category */
        if ($category->getDisplayNestedProducts()) {
            $criteria->addFilter(new EqualsFilter('product.categoriesRo.id', $request->getNavigationId()));
        } else {
            $criteria->addFilter(new EqualsFilter('product.categories.id', $request->getNavigationId()));
        }

        $this->eventDispatcher->dispatch(
            ListingEvents::CRITERIA_CREATED,
            new PageCriteriaCreatedEvent($criteria, $context, $request)
        );

        if (!$request->loadAggregations()) {
            $criteria->resetAggregations();
        }

        $products = $this->productRepository->search($criteria, $context);

        $page = new ListingPageStruct(
            $request->getNavigationId(),
            $category,
            $products,
            $criteria
        );

        $page->setShowListing(true);
        $page->setProductBoxLayout('basic');

        return $page;
    }
}
