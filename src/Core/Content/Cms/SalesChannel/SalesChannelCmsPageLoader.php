<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel;

use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent;
use Shopware\Core\Content\Cms\Events\CmsPageLoaderCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

#[Package('buyers-experience')]
class SalesChannelCmsPageLoader implements SalesChannelCmsPageLoaderInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<CmsPageCollection> $cmsPageRepository
     */
    public function __construct(
        private readonly EntityRepository $cmsPageRepository,
        private readonly CmsSlotsDataResolver $slotDataResolver,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function load(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        ?array $config = null,
        ?ResolverContext $resolverContext = null
    ): EntitySearchResult {
        $this->eventDispatcher->dispatch(new CmsPageLoaderCriteriaEvent($request, $criteria, $context));
        $config ??= [];

        // ensure sections, blocks and slots are loaded, slots and blocks can be restricted by caller
        $criteria->addAssociation('sections.backgroundMedia');
        $criteria->addAssociation('sections.blocks.backgroundMedia');
        $criteria->addAssociation('sections.blocks.slots');

        // step 1, load cms pages with blocks and slots
        $result = $this->cmsPageRepository->search($criteria, $context->getContext());
        $pages = $result->getEntities();

        foreach ($pages as $page) {
            if ($page->getSections() === null) {
                continue;
            }

            $page->getSections()->sort(fn (CmsSectionEntity $a, CmsSectionEntity $b) => $a->getPosition() <=> $b->getPosition());

            if (!$resolverContext) {
                $resolverContext = new ResolverContext($context, $request);
            }

            // step 2, sort blocks into sectionPositions
            foreach ($page->getSections() as $section) {
                $blocks = $section->getBlocks();
                if ($blocks === null) {
                    continue;
                }
                $blocks->sort(fn (CmsBlockEntity $a, CmsBlockEntity $b) => $a->getPosition() <=> $b->getPosition());

                foreach ($blocks as $block) {
                    $slots = $block->getSlots();
                    if ($slots === null) {
                        continue;
                    }
                    $slots->sort(fn (CmsSlotEntity $a, CmsSlotEntity $b) => $a->getSlot() <=> $b->getSlot());
                }
            }

            // step 3, find config overwrite
            $overwrite = $config[$page->getId()] ?? $config;

            // step 4, overwrite slot config
            $this->overwriteSlotConfig($page, $overwrite);

            // step 5, resolve slot data
            $this->loadSlotData($page, $resolverContext);
        }

        $this->eventDispatcher->dispatch(new CmsPageLoadedEvent($request, $pages, $context));

        return $result;
    }

    private function loadSlotData(CmsPageEntity $page, ResolverContext $resolverContext): void
    {
        $sections = $page->getSections();
        if ($sections === null) {
            return;
        }

        $blocks = $sections->getBlocks();
        $slots = $this->slotDataResolver->resolve($blocks->getSlots(), $resolverContext);

        $blocks->setSlots($slots);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function overwriteSlotConfig(CmsPageEntity $page, array $config): void
    {
        $sections = $page->getSections();
        if ($sections === null) {
            return;
        }

        foreach ($sections->getBlocks()->getSlots() as $slot) {
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
