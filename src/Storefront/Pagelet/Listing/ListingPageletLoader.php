<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\InternalRequest;
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
     * @var ContainerInterface
     */
    private $container;

    public function __construct(
        StorefrontProductRepository $productRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param ContainerInterface|null $container
     */
    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    /**
     * @param InternalRequest $request
     * @param CheckoutContext $context
     *
     * @return ListingPageletStruct
     */
    public function load(InternalRequest $request, CheckoutContext $context): ListingPageletStruct
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.active', true));
        $criteria->addFilter(new EqualsFilter('product.categoriesRo.id', $request->requireGet('categoryId')));

        $this->eventDispatcher->dispatch(
            ListingEvents::CRITERIA_CREATED,
            new PageCriteriaCreatedEvent($criteria, $context, $request)
        );

        $products = $this->productRepository->search($criteria, $context);

        $page = new ListingPageletStruct();
        $page->setPageCount(3);
        $page->setCurrentPage(1);
        $page->setNavigationId($request->requireGet('categoryId'));
        $page->setProducts($products);
        $page->setCriteria($criteria);

        $page->setShowListing(true);
        $page->setProductBoxLayout('basic');

        return $page;
    }
}
