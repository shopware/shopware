<?php declare(strict_types=1);

namespace Shopware\Storefront\Listing\PageLoader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Category\Storefront\StorefrontCategoryRepository;
use Shopware\Storefront\Framework\Page\PageRequest;
use Shopware\Storefront\Framework\PageLoader\PageLoader;
use Shopware\Storefront\Framework\Seo\DbalIndexing\SeoUrl\ListingPageSeoUrlIndexer;
use Shopware\Storefront\Listing\Page\NavigationPageletStruct;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class NavigationPageletLoader implements PageLoader
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
     * @param PageRequest     $request
     * @param CheckoutContext $context
     *
     * @return NavigationPageletStruct
     */
    public function load(PageRequest $request, CheckoutContext $context): NavigationPageletStruct
    {
        $pagelet = new NavigationPageletStruct();
        $navigation = $this->categoryService->read($this->getNavigationId($request->getHttpRequest()), $context->getContext());

        $pagelet->setTree($navigation->getTree());
        $pagelet->setActiveCategory($navigation->getActiveCategory());

        return $pagelet;
    }

    private function getNavigationId(Request $request): ?string
    {
        $route = $request->attributes->get('_route');

        switch ($route) {
            case ListingPageSeoUrlIndexer::ROUTE_NAME:
                return $request->attributes->get('_route_params')['id'];
        }

        return null;
    }
}
