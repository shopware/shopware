<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Menu\Offcanvas;

use Shopware\Core\Content\Navigation\NavigationEntity;
use Shopware\Core\Content\Navigation\Service\NavigationTreeLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Util\Tree\Tree;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class MenuOffcanvasPageletLoader implements PageLoaderInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var NavigationTreeLoader
     */
    private $navigationLoader;

    public function __construct(EventDispatcherInterface $eventDispatcher, NavigationTreeLoader $navigationLoader)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->navigationLoader = $navigationLoader;
    }

    public function load(Request $request, SalesChannelContext $context): MenuOffcanvasPagelet
    {
        $activeId = $context->getSalesChannel()->getNavigationId();
        $navigationId = $request->query->get('navigationId', $activeId);

        if (!$navigationId) {
            throw new MissingRequestParameterException('navigationId');
        }

        $navigation = $this->getCategoryTree((string) $navigationId, $context);

        $page = new MenuOffcanvasPagelet($navigation);

        $this->eventDispatcher->dispatch(
            MenuOffcanvasPageletLoadedEvent::NAME,
            new MenuOffcanvasPageletLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    /**
     * returns the category tree for the passed
     * navigation id
     * if the category has no children,
     * the parent category will be used
     */
    private function getCategoryTree(string $navigationId, SalesChannelContext $context)
    {
        /** @var Tree $navigation */
        $navigation = $this->navigationLoader->loadLevel($navigationId, $context);

        /** @var NavigationEntity $active */
        $active = $navigation->getActive();

        if ($active->getChildCount() > 0) {
            return $navigation;
        }

        return $this->navigationLoader->loadLevel($active->getParentId(), $context);
    }
}
