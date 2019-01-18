<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\NavigationSidebar;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Category\Storefront\NavigationLoader;
use Shopware\Core\Framework\Routing\InternalRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class NavigationSidebarPageletLoader
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var NavigationLoader
     */
    private $categoryService;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        NavigationLoader $categoryService,
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
     * @param InternalRequest $request
     * @param CheckoutContext $context
     *
     * @throws \Shopware\Core\Framework\Exception\MissingParameterException
     *
     * @return NavigationSidebarPageletStruct
     */
    public function load(InternalRequest $request, CheckoutContext $context): NavigationSidebarPageletStruct
    {
        $pagelet = new NavigationSidebarPageletStruct();
        $navigation = $this->categoryService->read($request->require('categoryId'), $context->getContext());

        $pagelet->setTree($navigation->getTree());
        $pagelet->setActiveCategory($navigation->getActiveCategory());

        return $pagelet;
    }
}
