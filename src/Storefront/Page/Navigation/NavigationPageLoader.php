<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Navigation;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotDataResolver;
use Shopware\Core\Content\Cms\Storefront\StorefrontCmsPageRepository;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class NavigationPageLoader
{
    /**
     * @var StorefrontCmsPageRepository
     */
    private $storefrontCmsPageRepository;

    /**
     * @var SlotDataResolver
     */
    private $slotDataResolver;

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
        EventDispatcherInterface $eventDispatcher,
        StorefrontCmsPageRepository $storefrontCmsPageRepository,
        SlotDataResolver $slotDataResolver
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->storefrontCmsPageRepository = $storefrontCmsPageRepository;
        $this->slotDataResolver = $slotDataResolver;
    }

    public function load($navigation, string $pageId, InternalRequest $request, CheckoutContext $context): NavigationPage
    {
        $page = $this->genericLoader->load($request, $context);
        $page = NavigationPage::createFrom($page);

        // step 1, load navigation

        // step 2, load cms structure
        $cmsPage = $this->getCmsPage($pageId, $context);

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

    private function overwriteSlotConfig(CmsPageEntity $page, $navigation): void
    {
    }

    private function loadSlotData(CmsPageEntity $page, InternalRequest $request, CheckoutContext $context): void
    {
        if (!$page->getBlocks()) {
            return;
        }

        $slots = $this->slotDataResolver->resolve(
            $page->getBlocks()->getSlots(),
            $request,
            $context
        );

        $page->getBlocks()->setSlots($slots);
    }

    private function getCmsPage(string $pageId, CheckoutContext $context): CmsPageEntity
    {
        $pages = $this->storefrontCmsPageRepository->read([$pageId], $context);

        if ($pages->count() === 0) {
            throw new PageNotFoundException($pageId);
        }

        /** @var CmsPageEntity $page */
        $page = $pages->first();

        return $page;
    }
}
