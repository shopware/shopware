<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel;

use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotDataResolver;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class SalesChannelCmsPageLoader
{
    /**
     * @var EntityRepositoryInterface
     */
    private $cmsPageRepository;

    /**
     * @var SlotDataResolver
     */
    private $slotDataResolver;

    public function __construct(EntityRepositoryInterface $cmsPageRepository, SlotDataResolver $slotDataResolver)
    {
        $this->cmsPageRepository = $cmsPageRepository;
        $this->slotDataResolver = $slotDataResolver;
    }

    public function load(Request $request, Criteria $criteria, SalesChannelContext $context, ?array $config = null, ?ResolverContext $resolverContext = null): EntitySearchResult
    {
        $config = $config ?? [];

        // ensure blocks and slots are loaded, slots and blocks can be restricted by caller
        $criteria->addAssociationPath('blocks.slots');
        $criteria->addAssociationPath('blocks.backgroundMedia');

        // step 1, load cms pages with blocks and slots
        $pages = $this->cmsPageRepository->search($criteria, $context->getContext());

        /** @var CmsPageEntity $page */
        foreach ($pages as $page) {
            if (!$page->getBlocks()) {
                continue;
            }

            if (!$resolverContext) {
                $resolverContext = new ResolverContext($context, $request);
            }

            // step 2, sort blocks by position for correct order
            $page->getBlocks()->sort(function (CmsBlockEntity $a, CmsBlockEntity $b) {
                return $a->getPosition() <=> $b->getPosition();
            });

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
        $slots = $this->slotDataResolver->resolve($page->getBlocks()->getSlots(), $resolverContext);

        $page->getBlocks()->setSlots($slots);
    }

    private function overwriteSlotConfig(CmsPageEntity $page, array $config): void
    {
        if (empty($config)) {
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
            $slot->addTranslated('config', $merged);
        }
    }
}
