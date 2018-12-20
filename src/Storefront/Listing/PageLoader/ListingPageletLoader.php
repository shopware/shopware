<?php declare(strict_types=1);

namespace Shopware\Storefront\Listing\PageLoader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Framework\PageLoader\PageLoader;
use Shopware\Storefront\Listing\Event\ListingPageLoadedEvent;
use Shopware\Storefront\Listing\Event\PageCriteriaCreatedEvent;
use Shopware\Storefront\Listing\ListingEvents;
use Shopware\Storefront\Listing\Page\ListingPageletStruct;
use Shopware\Storefront\Listing\Page\ListingPageRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ListingPageletLoader implements PageLoader
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
     * @param ListingPageRequest $request
     * @param CheckoutContext    $context
     *
     * @return ListingPageletStruct
     */
    public function load(ListingPageRequest $request, CheckoutContext $context): ListingPageletStruct
    {
        /** @var ListingPageRequest $request */
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.active', true));
        $criteria->addFilter(new EqualsFilter('product.categoriesRo.id', $request->getNavigationId()));

        $this->eventDispatcher->dispatch(
            ListingEvents::CRITERIA_CREATED,
            new PageCriteriaCreatedEvent($criteria, $context, $request)
        );

        if (!$request->loadAggregations()) {
            $criteria->resetAggregations();
        }

        $products = $this->productRepository->search($criteria, $context);

        $page = new ListingPageletStruct($request->getNavigationId(), $products, $criteria);

        $page->setShowListing(true);
        $page->setProductBoxLayout('basic');

        $this->eventDispatcher->dispatch(
            ListingEvents::LOADED,
            new ListingPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
