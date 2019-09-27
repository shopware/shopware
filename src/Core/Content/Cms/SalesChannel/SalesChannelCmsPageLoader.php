<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel;

use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class SalesChannelCmsPageLoader
{
    /**
     * @var EntityRepositoryInterface
     */
    private $cmsPageRepository;

    /**
     * @var CmsSlotsDataResolver
     */
    private $slotDataResolver;

    public function __construct(EntityRepositoryInterface $cmsPageRepository, CmsSlotsDataResolver $slotDataResolver)
    {
        $this->cmsPageRepository = $cmsPageRepository;
        $this->slotDataResolver = $slotDataResolver;
    }

    public function load(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        ?array $config = null,
        ?ResolverContext $resolverContext = null
    ): EntitySearchResult {
        $config = $config ?? [];

        // ensure sections, blocks and slots are loaded, slots and blocks can be restricted by caller
        $criteria->getAssociation('sections')
            ->addSorting(new FieldSorting('position', FieldSorting::ASCENDING))
            ->addAssociation('backgroundMedia');

        $criteria->getAssociation('sections.blocks')
            ->addAssociation('backgroundMedia')
            ->addAssociation('slots')
            ->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));

        // step 1, load cms pages with blocks and slots
        $pages = $this->cmsPageRepository->search($criteria, $context->getContext());

        /** @var CmsPageEntity $page */
        foreach ($pages as $page) {
            if (!$page->getSections()) {
                continue;
            }

            if (!$resolverContext) {
                $resolverContext = new ResolverContext($context, $request);
            }

            // step 2, sort blocks into sectionPositions
            /* @var $section CmsSectionEntity */
            foreach ($page->getSections() as $section) {
                /* @var $sectionBlock CmsBlockCollection */
                $sectionBlock = $section->getBlocks();

                $sectionBlockPositions = [];

                foreach ($sectionBlock as $block) {
                    $key = $block->getSectionPosition() . 'Blocks';

                    if (!isset($sectionBlockPositions[$key])) {
                        $sectionBlockPositions[$key] = new CmsBlockCollection();
                    }

                    $sectionBlockPositions[$key]->add($block);
                }

                foreach ($sectionBlockPositions as $postionName => $blocksCollection) {
                    $blocksCollection->sort(function (CmsBlockEntity $a, CmsBlockEntity $b) {
                        return $a->getPosition() <=> $b->getPosition();
                    });

                    $section->addExtension($postionName, $blocksCollection);
                }
            }

            // step 3, find config overwrite
            $overwrite = $config[$page->getId()] ?? $config;

            // step 4, overwrite slot config
            $this->overwriteSlotConfig($page, $overwrite);

            // step 5, resolve slot data
            $this->loadSlotData($page, $resolverContext);
        }

        return $pages;
    }

    private function loadSlotData(CmsPageEntity $page, ResolverContext $resolverContext): void
    {
        $slots = $this->slotDataResolver->resolve($page->getSections()->getBlocks()->getSlots(), $resolverContext);

        $page->getSections()->getBlocks()->setSlots($slots);
    }

    private function overwriteSlotConfig(CmsPageEntity $page, array $config): void
    {
        if (empty($config)) {
            return;
        }

        /** @var CmsSlotEntity $slot */
        foreach ($page->getSections()->getBlocks()->getSlots() as $slot) {
            if (!isset($config[$slot->getId()])) {
                continue;
            }

            $merged = array_replace_recursive(
                $slot->getConfig(),
                $config[$slot->getId()]
            );

            $slot->setConfig($merged);
            $slot->addTranslated('config', $merged);
        }
    }
}
