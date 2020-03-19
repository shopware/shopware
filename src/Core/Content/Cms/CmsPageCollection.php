<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void               add(CmsPageEntity $entity)
 * @method void               set(string $key, CmsPageEntity $entity)
 * @method CmsPageEntity[]    getIterator()
 * @method CmsPageEntity[]    getElements()
 * @method CmsPageEntity|null get(string $key)
 * @method CmsPageEntity|null first()
 * @method CmsPageEntity|null last()
 */
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
