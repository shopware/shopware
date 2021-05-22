<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel;

use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent;
use Shopware\Core\Content\Cms\Events\CmsPageLoaderCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class SalesChannelCmsPageLoader implements SalesChannelCmsPageLoaderInterface
{
    private EntityRepositoryInterface $cmsPageRepository;

    private CmsSlotsDataResolver $slotDataResolver;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        EntityRepositoryInterface $cmsPageRepository,
        CmsSlotsDataResolver $slotDataResolver,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->cmsPageRepository = $cmsPageRepository;
        $this->slotDataResolver = $slotDataResolver;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        ?array $config = null,
        ?ResolverContext $resolverContext = null
    ): EntitySearchResult {
        $this->eventDispatcher->dispatch(new CmsPageLoaderCriteriaEvent($request, $criteria, $context));
        $config = $config ?? [];

        // ensure sections, blocks and slots are loaded, slots and blocks can be restricted by caller
        $criteria
            ->getAssociation('sections')
            ->addAssociation('backgroundMedia');

        $criteria
            ->getAssociation('sections.blocks')
            ->addAssociation('backgroundMedia')
            ->addAssociation('slots');

        // step 1, load cms pages with blocks and slots
        $pages = $this->cmsPageRepository->search($criteria, $context->getContext());

        foreach ($pages as $page) {
            if ($page->getSections() === null) {
                continue;
            }

            $page->getSections()->sort(function (CmsSectionEntity $a, CmsSectionEntity $b) {
                return $a->getPosition() <=> $b->getPosition();
            });

            if (!$resolverContext) {
                $resolverContext = new ResolverContext($context, $request);
            }

            // step 2, sort blocks into sectionPositions
            foreach ($page->getSections() as $section) {
                $section->getBlocks()->sort(function (CmsBlockEntity $a, CmsBlockEntity $b) {
                    return $a->getPosition() <=> $b->getPosition();
                });
            }

            // step 3, find config overwrite
            $overwrite = $config[$page->getId()] ?? $config;

            // step 4, overwrite slot config
            $this->overwriteSlotConfig($page, $overwrite);

            // step 5, resolve slot data
            $this->loadSlotData($page, $resolverContext);
        }

        $this->eventDispatcher->dispatch(new CmsPageLoadedEvent($request, $pages->getEntities(), $context));

        return $pages;
    }

    private function loadSlotData(CmsPageEntity $page, ResolverContext $resolverContext): void
    {
        $slots = $this->slotDataResolver->resolve($page->getSections()->getBlocks()->getSlots(), $resolverContext);

        $page->getSections()->getBlocks()->setSlots($slots);
    }

    private function overwriteSlotConfig(CmsPageEntity $page, array $config): void
    {
        foreach ($page->getSections()->getBlocks()->getSlots() as $slot) {
            if ($slot->getConfig() === null && $slot->getTranslation('config') !== null) {
                $slot->setConfig($slot->getTranslation('config'));
            }

            if (empty($config)) {
                continue;
            }

            if (!isset($config[$slot->getId()])) {
                continue;
            }

            $defaultConfig = $slot->getConfig() ?? [];
            $merged = array_replace_recursive(
                $defaultConfig,
                $config[$slot->getId()]
            );

            $slot->setConfig($merged);
            $slot->addTranslated('config', $merged);
        }
    }
}
