<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property\Aggregate\PropertyGroupOption;

use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PropertyGroupOptionEntity>
 */
#[Package('inventory')]
class PropertyGroupOptionCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getPropertyGroupIds(): array
    {
        return $this->fmap(fn (PropertyGroupOptionEntity $propertyGroupOption) => $propertyGroupOption->getGroupId());
    }

    public function filterByGroupId(string $id): self
    {
        return $this->filter(fn (PropertyGroupOptionEntity $propertyGroupOption) => $propertyGroupOption->getGroupId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getMediaIds(): array
    {
        return $this->fmap(fn (PropertyGroupOptionEntity $propertyGroupOption) => $propertyGroupOption->getMediaId());
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(fn (PropertyGroupOptionEntity $propertyGroupOption) => $propertyGroupOption->getMediaId() === $id);
    }

    public function getGroups(): PropertyGroupCollection
    {
        return new PropertyGroupCollection(
            $this->fmap(fn (PropertyGroupOptionEntity $propertyGroupOption) => $propertyGroupOption->getGroup())
        );
    }

    public function groupByPropertyGroups(): PropertyGroupCollection
    {
        $groups = new PropertyGroupCollection();

        foreach ($this->getIterator() as $element) {
            if ($groups->has($element->getGroupId())) {
                $group = $groups->get($element->getGroupId());
            } else {
                $group = PropertyGroupEntity::createFrom($element->getGroup() ?? new PropertyGroupEntity());
                $groups->add($group);

                $group->setOptions(new self());
            }

            if ($group->getOptions()) {
                $group->getOptions()->add($element);
            }
        }

        return $groups;
    }

    public function getApiAlias(): string
    {
        return 'product_group_option_collection';
    }

    protected function getExpectedClass(): string
    {
        return PropertyGroupOptionEntity::class;
    }
}
