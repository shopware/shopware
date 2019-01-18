<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Home;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class HomePageLoader implements PageLoaderInterface
{
    /**
     * @var PageWithHeaderLoader
     */
    private $genericLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        PageWithHeaderLoader $genericLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->genericLoader = $genericLoader;
    }

    public function load(InternalRequest $request, CheckoutContext $context): HomePage
    {
        $page = $this->genericLoader->load($request, $context);

        $page = HomePage::createFrom($page);

        $this->eventDispatcher->dispatch(
            HomePageLoadedEvent::NAME,
            new HomePageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
