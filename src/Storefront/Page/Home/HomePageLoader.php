<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Home;

use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class HomePageLoader implements PageLoaderInterface
{
    /**
     * @var PageWithHeaderLoader|PageLoaderInterface
     */
    private $genericLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        PageLoaderInterface $genericLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->genericLoader = $genericLoader;
    }

    public function load(InternalRequest $request, SalesChannelContext $context): HomePage
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
