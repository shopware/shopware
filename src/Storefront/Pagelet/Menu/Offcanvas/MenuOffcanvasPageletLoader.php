<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Menu\Offcanvas;

use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Content\Category\Tree\Tree;
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

        $navigation = $this->getCategoryTree((string) $navigationId, $salesChannelContext);
        $page = new MenuOffcanvasPagelet($navigation);

        $this->eventDispatcher->dispatch(
            new MenuOffcanvasPageletLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    /**
     * Returns the category tree for the passed navigation id.
     * If the category has no children, the parent category will be used
     *
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    private function getCategoryTree(string $navigationId, SalesChannelContext $context): Tree
    {
        $category = $this->navigationLoader->loadLevel($navigationId, $context);
        $active = $category->getActive();

        if ($active->getChildCount() > 0 || $active->getParentId() === null) {
            return $category;
        }

        return $this->navigationLoader->loadLevel($active->getParentId(), $context);
    }
}
