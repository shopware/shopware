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

    public function sortByPositions(): void
    {
        usort($this->elements, function (PropertyGroupEntity $a, PropertyGroupEntity $b) {
            $posA = $a->getTranslation('position');
            $posB = $b->getTranslation('position');
            if ($posA === $posB) {
                return strnatcmp($a->getTranslation('name'), $b->getTranslation('name'));
            }

            return $posA <=> $posB;
        });
    }

    public function getApiAlias(): string
    {
        return 'product_group_collection';
    }

    protected function getExpectedClass(): string
    {
        return PropertyGroupEntity::class;
    }
}
