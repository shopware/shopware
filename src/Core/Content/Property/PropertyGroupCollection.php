<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                     add(PropertyGroupEntity $entity)
 * @method void                     set(string $key, PropertyGroupEntity $entity)
 * @method PropertyGroupEntity[]    getIterator()
 * @method PropertyGroupEntity[]    getElements()
 * @method PropertyGroupEntity|null get(string $key)
 * @method PropertyGroupEntity|null first()
 * @method PropertyGroupEntity|null last()
 */
class PropertyGroupCollection extends EntityCollection
{
    public function getOptionIdMap(): array
    {
        $map = [];
        /** @var PropertyGroupEntity $group */
        foreach ($this->elements as $group) {
            if (!$group->getOptions()) {
                continue;
            }

            foreach ($group->getOptions() as $option) {
                $map[$option->getId()] = $group->getId();
            }
        }

        return $map;
    }

    protected function getExpectedClass(): string
    {
        return PropertyGroupEntity::class;
    }
}
