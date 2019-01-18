<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Search;

use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Category\Storefront\NavigationLoader;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Pagelet\Listing\PageCriteriaCreatedEvent;
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
     * @var NavigationLoader
     */
    private $categoryService;

    public function __construct(
        StorefrontProductRepository $productRepository,
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService,
        NavigationLoader $categoryService
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

    /**
     * @param InternalRequest $request
     * @param CheckoutContext $context
     *
     * @return SearchPageletStruct
     */
    public function load(InternalRequest $request, CheckoutContext $context): SearchPageletStruct
    {
        $config = [];

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.active', 1));

        $this->eventDispatcher->dispatch(
            PageCriteriaCreatedEvent::NAME,
            new PageCriteriaCreatedEvent($criteria, $context, $request)
        );

        $products = $this->productRepository->search($criteria, $context);

        $layout = $config['searchProductBoxLayout'] ?? 'basic';

        $page = new SearchPageletStruct();
        $page->setNavigationId(null);
        $page->setProducts($products);
        $page->setCriteria($criteria);
        $page->setProductBoxLayout($layout);
//        $page->setSearchTerm($request->getSearchTerm());

        return $page;
    }
}
