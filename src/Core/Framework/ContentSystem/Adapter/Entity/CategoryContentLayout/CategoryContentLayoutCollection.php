<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter\Entity\CategoryContentLayout;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 *
 * @extends EntityCollection<CategoryContentLayoutEntity>
 */
#[Package('framework')]
class CategoryContentLayoutCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'category_content_layout_collection';
    }

    protected function getExpectedClass(): string
    {
        return CategoryContentLayoutEntity::class;
    }
}
