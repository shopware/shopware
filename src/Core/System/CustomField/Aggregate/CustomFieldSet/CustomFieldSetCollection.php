<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField\Aggregate\CustomFieldSet;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                      add(CustomFieldSetEntity $entity)
 * @method void                      set(string $key, CustomFieldSetEntity $entity)
 * @method CustomFieldSetEntity[]    getIterator()
 * @method CustomFieldSetEntity[]    getElements()
 * @method CustomFieldSetEntity|null get(string $key)
 * @method CustomFieldSetEntity|null first()
 * @method CustomFieldSetEntity|null last()
 */
class CustomFieldSetCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'custom_field_set_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomFieldSetEntity::class;
    }
}
