<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                              add(CustomFieldSetRelationEntity $entity)
 * @method void                              set(string $key, CustomFieldSetRelationEntity $entity)
 * @method CustomFieldSetRelationEntity[]    getIterator()
 * @method CustomFieldSetRelationEntity[]    getElements()
 * @method CustomFieldSetRelationEntity|null get(string $key)
 * @method CustomFieldSetRelationEntity|null first()
 * @method CustomFieldSetRelationEntity|null last()
 */
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
