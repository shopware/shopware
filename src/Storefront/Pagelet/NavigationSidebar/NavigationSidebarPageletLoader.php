<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\NavigationSidebar;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Category\Storefront\StorefrontCategoryRepository;
use Shopware\Storefront\Framework\Seo\DbalIndexing\SeoUrl\ListingPageSeoUrlIndexer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class NavigationSidebarPageletLoader
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var StorefrontCategoryRepository
     */
    private $categoryService;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        StorefrontCategoryRepository $categoryService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->categoryService = $categoryService;
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
     * @param NavigationSidebarPageletRequest $request
     * @param CheckoutContext                 $context
     *
     * @return NavigationSidebarPageletStruct
     */
    public function load(NavigationSidebarPageletRequest $request, CheckoutContext $context): NavigationSidebarPageletStruct
    {
        $pagelet = new NavigationSidebarPageletStruct();
        $navigation = $this->categoryService->read($this->getNavigationId($request), $context->getContext());

        $pagelet->setTree($navigation->getTree());
        $pagelet->setActiveCategory($navigation->getActiveCategory());

        return $pagelet;
    }

    private function getNavigationId(NavigationSidebarPageletRequest $request): ?string
    {
        $route = $request->getRoute();

        switch ($route) {
            case ListingPageSeoUrlIndexer::ROUTE_NAME:
            default:
                return $request->getNavigationId();
        }
    }
}
