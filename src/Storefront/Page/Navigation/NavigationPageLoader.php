<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Navigation;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageRepository;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotDataResolver;
use Shopware\Core\Content\Navigation\NavigationEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class NavigationPageLoader implements PageLoaderInterface
{
    /**
     * @var SalesChannelCmsPageRepository
     */
    private $cmsPageRepository;

    /**
     * @var SlotDataResolver
     */
    private $slotDataResolver;

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
        EventDispatcherInterface $eventDispatcher,
        SalesChannelCmsPageRepository $storefrontCmsPageRepository,
        SlotDataResolver $slotDataResolver
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->cmsPageRepository = $storefrontCmsPageRepository;
        $this->slotDataResolver = $slotDataResolver;
    }

    public function load(Request $request, SalesChannelContext $context): NavigationPage
    {
        $page = $this->genericLoader->load($request, $context);
        $page = NavigationPage::createFrom($page);

        /** @var NavigationEntity $navigation */
        // step 1, load navigation
        $navigation = $page->getHeader()->getNavigation()->getActive();

        // step 2, load cms structure
        $cmsPage = $this->getCmsPage($navigation->getCmsPageId(), $context);

        // step 3, overwrite slot config
        $this->overwriteSlotConfig($cmsPage, $navigation);

        // step 4, resolve slot data
        $this->loadSlotData($cmsPage, $request, $context);

        $page->setCmsPage($cmsPage);

        $this->eventDispatcher->dispatch(
            NavigationPageLoadedEvent::NAME,
            new NavigationPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function overwriteSlotConfig(CmsPageEntity $page, NavigationEntity $navigation): void
    {
        $config = $navigation->getSlotConfig();

        if (!$config || !$page->getBlocks()) {
            return;
        }

        /** @var CmsSlotEntity $slot */
        foreach ($page->getBlocks()->getSlots() as $slot) {
            if (!isset($config[$slot->getId()])) {
                continue;
            }

            $merged = array_replace_recursive(
                $slot->getConfig(),
                $config[$slot->getId()]
            );

            $slot->setConfig($merged);
        }
    }

    private function loadSlotData(CmsPageEntity $page, Request $request, SalesChannelContext $context): void
    {
        if (!$page->getBlocks()) {
            return;
        }

        $resolverContext = new ResolverContext($context, $request);
        $slots = $this->slotDataResolver->resolve($page->getBlocks()->getSlots(), $resolverContext);

        $page->getBlocks()->setSlots($slots);
    }

    private function getCmsPage(string $pageId, SalesChannelContext $context): CmsPageEntity
    {
        $pages = $this->cmsPageRepository->read([$pageId], $context);

        if ($pages->count() === 0) {
            throw new PageNotFoundException($pageId);
        }

        /** @var CmsPageEntity $page */
        $page = $pages->first();

        return $page;
    }
}
