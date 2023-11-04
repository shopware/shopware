<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CmsPageEntity>
 */
#[Package('content')]
class CmsPageCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'cms_page_collection';
    }

    protected function getExpectedClass(): string
    {
        return CmsPageEntity::class;
    }
}
