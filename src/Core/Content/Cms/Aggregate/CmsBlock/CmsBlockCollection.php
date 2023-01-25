<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsBlock;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CmsBlockEntity>
 */
#[Package('content')]
class CmsBlockCollection extends EntityCollection
{
    public function getSlots(): CmsSlotCollection
    {
        $slots = new CmsSlotCollection();

        foreach ($this->getIterator() as $block) {
            if (!$block->getSlots()) {
                continue;
            }

            $slots->merge($block->getSlots());
        }

        return $slots;
    }

    public function filterBySectionPosition(string $position): CmsBlockCollection
    {
        return $this->filter(fn (CmsBlockEntity $entity) => $entity->getSectionPosition() === $position);
    }

    public function setSlots(CmsSlotCollection $slots): void
    {
        foreach ($this->getIterator() as $block) {
            $blockSlots = $block->getSlots();
            if (!$blockSlots) {
                continue;
            }

            foreach ($blockSlots->getIds() as $slotId) {
                $blockSlots->set($slotId, $slots->get($slotId));
            }
        }
    }

    public function getApiAlias(): string
    {
        return 'cms_page_block_collection';
    }

    protected function getExpectedClass(): string
    {
        return CmsBlockEntity::class;
    }
}
