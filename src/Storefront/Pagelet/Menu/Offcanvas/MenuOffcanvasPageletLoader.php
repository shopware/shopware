<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Menu\Offcanvas;

use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class MenuOffcanvasPageletLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var NavigationLoader
     */
    private $navigationLoader;

    public function __construct(EventDispatcherInterface $eventDispatcher, NavigationLoader $navigationLoader)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->navigationLoader = $navigationLoader;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): MenuOffcanvasPagelet
    {
        $navigationId = $request->query->get('navigationId', $salesChannelContext->getSalesChannel()->getNavigationCategoryId());
        if (!$navigationId) {
            throw new MissingRequestParameterException('navigationId');
        }

        $tree = $this->navigationLoader->loadLevel($navigationId, $salesChannelContext);

        $page = new MenuOffcanvasPagelet($tree);

        $this->eventDispatcher->dispatch(
            new MenuOffcanvasPageletLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }
}
