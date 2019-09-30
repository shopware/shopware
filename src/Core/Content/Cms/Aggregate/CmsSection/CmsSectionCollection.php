<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsSection;

use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                  add(CmsSectionEntity $entity)
 * @method void                  set(string $key, CmsSectionEntity $entity)
 * @method CmsSectionEntity[]    getIterator()
 * @method CmsSectionEntity[]    getElements()
 * @method CmsSectionEntity|null get(string $key)
 * @method CmsSectionEntity|null first()
 * @method CmsSectionEntity|null last()
 */
class CmsSectionCollection extends EntityCollection
{
    public function getBlocks(): CmsBlockCollection
    {
        $blocks = new CmsBlockCollection();

        foreach ($this->getIterator() as $section) {
            if (!$section->getBlocks()) {
                continue;
            }

            $blocks->merge($section->getBlocks());
        }

        return $blocks;
    }

    public function setBlocks(CmsBlockCollection $blocks): void
    {
        foreach ($this->getIterator() as $section) {
            $sectionBlocks = $section->getBlocks();
            if (!$sectionBlocks) {
                continue;
            }

            foreach ($sectionBlocks->getIds() as $blockId) {
                $sectionBlocks->set($blockId, $blocks->get($blockId));
            }
        }
    }

    protected function getExpectedClass(): string
    {
        return CmsSectionEntity::class;
    }
}
