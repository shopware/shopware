<?php declare(strict_types=1);

namespace Shopware\Storefront\Search\PageLoader;

use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Category\Storefront\StorefrontCategoryRepository;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Listing\Event\ListingPageLoadedEvent;
use Shopware\Storefront\Listing\Event\PageCriteriaCreatedEvent;
use Shopware\Storefront\Search\Page\SearchPageletStruct;
use Shopware\Storefront\Search\Page\SearchPageRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SearchPageletLoader
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

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var StorefrontCategoryRepository
     */
    private $categoryService;

    public function __construct(
        StorefrontProductRepository $productRepository,
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService,
        StorefrontCategoryRepository $categoryService
    ) {
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->cartService = $cartService;
        $this->categoryService = $categoryService;
    }

    /**
     * @param ContainerInterface|null $container
     */
    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    public function load(SearchPageRequest $request, CheckoutContext $context): SearchPageletStruct
    {
        $config = [];

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.active', 1));

        $this->eventDispatcher->dispatch(
            PageCriteriaCreatedEvent::NAME,
            new PageCriteriaCreatedEvent($criteria, $context, $request)
        );

        if (!$request->loadAggregations()) {
            $criteria->resetAggregations();
        }

        $products = $this->productRepository->search($criteria, $context);

        $layout = $config['searchProductBoxLayout'] ?? 'basic';

        $page = new SearchPageletStruct(null, $products, $criteria);
        $page->setProductBoxLayout($layout);
        $page->setSearchTerm($request->getSearchTerm());

        $this->eventDispatcher->dispatch(
            ListingPageLoadedEvent::NAME,
            new ListingPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
