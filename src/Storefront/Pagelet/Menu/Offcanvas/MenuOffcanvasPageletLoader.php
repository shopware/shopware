<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Menu\Offcanvas;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Content\Category\Tree\Tree;
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
     * @var NavigationLoader
     */
    private $navigationLoader;

    public function __construct(EventDispatcherInterface $eventDispatcher, NavigationLoader $navigationLoader)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->navigationLoader = $navigationLoader;
    }

    public function load(Request $request, SalesChannelContext $context): MenuOffcanvasPagelet
    {
        $navigationId = $request->query->get('navigationId', $context->getSalesChannel()->getCategoryId());
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
        /** @var Tree $category */
        $category = $this->navigationLoader->loadLevel($navigationId, $context);

        /** @var CategoryEntity $active */
        $active = $category->getActive();

        if ($active->getChildCount() > 0 || $active->getParentId() === null) {
            return $category;
        }

        return $this->navigationLoader->loadLevel($active->getParentId(), $context);
    }
}
