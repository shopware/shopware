<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsSection;

use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CmsSectionEntity>
 */
#[Package('content')]
class CmsSectionCollection extends EntityCollection
{
    public function getBlocks(): CmsBlockCollection
    {
        $blocks = new CmsBlockCollection();

        /** @var CmsSectionEntity $section */
        foreach ($this->elements as $section) {
            if (!$section->getBlocks()) {
                continue;
            }

            $blocks->merge($section->getBlocks());
        }

        return $blocks;
    }

    public function getApiAlias(): string
    {
        return 'cms_page_section_collection';
    }

    protected function getExpectedClass(): string
    {
        return CmsSectionEntity::class;
    }
}
