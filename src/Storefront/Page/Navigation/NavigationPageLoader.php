<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Navigation;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class NavigationPageLoader implements PageLoaderInterface
{
    /**
     * @var SalesChannelCmsPageLoader
     */
    private $cmsPageLoader;

    /**
     * @var PageWithHeaderLoader|PageLoaderInterface
     */
    private $genericLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EntityDefinition
     */
    private $categoryDefinition;

    public function __construct(
        SalesChannelCmsPageLoader $cmsPageLoader,
        PageLoaderInterface $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        EntityDefinition $categoryDefinition
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->cmsPageLoader = $cmsPageLoader;
        $this->categoryDefinition = $categoryDefinition;
    }

    public function load(Request $request, SalesChannelContext $context): NavigationPage
    {
        $page = $this->genericLoader->load($request, $context);
        $page = NavigationPage::createFrom($page);

        /** @var CategoryEntity $category */
        $category = $page->getHeader()->getNavigation()->getActive();

        $pageId = $category->getCmsPageId();

        if ($pageId) {
            $resolverContext = new EntityResolverContext($context, $request, $this->categoryDefinition, $category);

            $pages = $this->cmsPageLoader->load(
                $request,
                new Criteria([$pageId]),
                $context,
                $category->getSlotConfig(),
                $resolverContext
            );

            if (!$pages->has($pageId)) {
                throw new PageNotFoundException($pageId);
            }

            $page->setCmsPage($pages->get($pageId));
        }

        $this->eventDispatcher->dispatch(
            NavigationPageLoadedEvent::NAME,
            new NavigationPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
