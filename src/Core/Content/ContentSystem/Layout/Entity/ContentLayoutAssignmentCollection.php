<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 *
 * @extends EntityCollection<ContentLayoutAssignmentEntity>
 */
#[Package('discovery')]
class ContentLayoutAssignmentCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'content_layout_assignment_collection';
    }

    protected function getExpectedClass(): string
    {
        return ContentLayoutAssignmentEntity::class;
    }
}
