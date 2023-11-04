<?php declare(strict_types=1);

namespace Shopware\Core\System\Tag;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<TagEntity>
 */
#[Package('core')]
class TagCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'tag_collection';
    }

    protected function getExpectedClass(): string
    {
        return TagEntity::class;
    }
}
