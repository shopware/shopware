<?php declare(strict_types=1);

namespace Shopware\Core\System\Tag;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package core
 * @extends EntityCollection<TagEntity>
 */
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
