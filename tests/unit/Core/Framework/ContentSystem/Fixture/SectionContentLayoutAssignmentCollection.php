<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Fixture;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 *
 * @extends EntityCollection<SectionContentLayoutAssignmentEntity>
 */
final class SectionContentLayoutAssignmentCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'section_content_layout_assignment_collection';
    }

    protected function getExpectedClass(): string
    {
        return SectionContentLayoutAssignmentEntity::class;
    }
}
