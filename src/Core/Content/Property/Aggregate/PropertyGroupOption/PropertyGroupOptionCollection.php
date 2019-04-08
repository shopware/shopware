<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property\Aggregate\PropertyGroupOption;

use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                           add(PropertyGroupOptionEntity $entity)
 * @method void                           set(string $key, PropertyGroupOptionEntity $entity)
 * @method PropertyGroupOptionEntity[]    getIterator()
 * @method PropertyGroupOptionEntity[]    getElements()
 * @method PropertyGroupOptionEntity|null get(string $key)
 * @method PropertyGroupOptionEntity|null first()
 * @method PropertyGroupOptionEntity|null last()
 */
class PropertyGroupOptionCollection extends EntityCollection
{
    public function getPropertyGroupIds(): array
    {
        return $this->fmap(function (PropertyGroupOptionEntity $propertyGroupOption) {
            return $propertyGroupOption->getGroupId();
        });
    }

    public function filterByGroupId(string $id): self
    {
        return $this->filter(function (PropertyGroupOptionEntity $propertyGroupOption) use ($id) {
            return $propertyGroupOption->getGroupId() === $id;
        });
    }

    public function getMediaIds(): array
    {
        return $this->fmap(function (PropertyGroupOptionEntity $propertyGroupOption) {
            return $propertyGroupOption->getMediaId();
        });
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(function (PropertyGroupOptionEntity $propertyGroupOption) use ($id) {
            return $propertyGroupOption->getMediaId() === $id;
        });
    }

    public function getGroups(): PropertyGroupCollection
    {
        return new PropertyGroupCollection(
            $this->fmap(function (PropertyGroupOptionEntity $propertyGroupOption) {
                return $propertyGroupOption->getGroup();
            })
        );
    }

    public function groupByPropertyGroups(): PropertyGroupCollection
    {
        $groups = new PropertyGroupCollection();

        foreach ($this->getIterator() as $element) {
            if ($groups->has($element->getGroupId())) {
                $group = $groups->get($element->getGroupId());
            } else {
                $group = PropertyGroupEntity::createFrom($element->getGroup());
                $groups->add($group);

                $group->setOptions(new self());
            }

            $group->getOptions()->add($element);
        }

        return $groups;
    }

    protected function getExpectedClass(): string
    {
        return PropertyGroupOptionEntity::class;
    }
}
