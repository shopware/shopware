<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
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
            if ($group->getOptions() === null) {
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
            $posA = $a->getTranslation('position') ?? $a->getPosition() ?? 0;
            $posB = $b->getTranslation('position') ?? $b->getPosition() ?? 0;
            if ($posA === $posB) {
                return strnatcmp($a->getTranslation('name'), $b->getTranslation('name'));
            }

            return $posA <=> $posB;
        });
    }

    public function sortByConfig(): void
    {
        /** @var PropertyGroupEntity $group */
        foreach ($this->elements as $group) {
            if ($group->getOptions() === null) {
                continue;
            }

            $group->getOptions()->sort(static function (PropertyGroupOptionEntity $a, PropertyGroupOptionEntity $b) use ($group) {
                if ($group->getSortingType() === PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC) {
                    return strnatcmp($a->getTranslation('name'), $b->getTranslation('name'));
                }

                return ($a->getTranslation('position') ?? $a->getPosition() ?? 0) <=> ($b->getTranslation('position') ?? $b->getPosition() ?? 0);
            });
        }
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
