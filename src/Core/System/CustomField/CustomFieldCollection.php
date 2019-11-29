<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                   add(CustomFieldEntity $entity)
 * @method void                   set(string $key, CustomFieldEntity $entity)
 * @method CustomFieldEntity[]    getIterator()
 * @method CustomFieldEntity[]    getElements()
 * @method CustomFieldEntity|null get(string $key)
 * @method CustomFieldEntity|null first()
 * @method CustomFieldEntity|null last()
 */
class CustomFieldCollection extends EntityCollection
{
    public function filterByType(string $type): self
    {
        return $this->filter(function (CustomFieldEntity $attribute) use ($type) {
            return $attribute->getType() === $type;
        });
    }

    protected function getExpectedClass(): string
    {
        return CustomFieldEntity::class;
    }
}
