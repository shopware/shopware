<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CustomFieldSetRelationEntity>
 */
#[Package('system-settings')]
class CustomFieldSetRelationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'custom_field_set_relation_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomFieldSetRelationEntity::class;
    }
}
