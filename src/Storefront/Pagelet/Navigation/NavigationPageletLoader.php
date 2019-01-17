<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Navigation;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Category\Storefront\StorefrontCategoryRepository;
use Shopware\Storefront\Framework\Seo\DbalIndexing\SeoUrl\ListingPageSeoUrlIndexer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class NavigationPageletLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var StorefrontCategoryRepository
     */
    private $categoryService;

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
     * @param NavigationPageletRequest $request
     * @param CheckoutContext          $context
     *
     * @return NavigationPageletStruct
     */
    public function load(NavigationPageletRequest $request, CheckoutContext $context): NavigationPageletStruct
    {
        $pagelet = new NavigationPageletStruct();
        $navigation = $this->categoryService->read($this->getNavigationId($request), $context->getContext());

        $pagelet->setTree($navigation->getTree());
        $pagelet->setActiveCategory($navigation->getActiveCategory());

        return $pagelet;
    }

    private function getNavigationId(NavigationPageletRequest $request): ?string
    {
        $route = $request->getRoute();

        if ($request->getRouteParams()) {
            switch ($route) {
                case ListingPageSeoUrlIndexer::ROUTE_NAME:
                default:
                    return $request->getNavigationId();
            }
        }

        return null;
    }
}
