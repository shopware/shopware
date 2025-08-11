<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PropertyGroupEntity>
 */
#[Package('inventory')]
class PropertyGroupCollection extends EntityCollection
{
    /**
     * @return array<string, string>
     */
    public function getOptionIdMap(): array
    {
        $map = [];

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
        uasort($this->elements, function (PropertyGroupEntity $a, PropertyGroupEntity $b) {
            $posA = $a->getTranslation('position') ?? $a->getPosition() ?? 0;
            $posB = $b->getTranslation('position') ?? $b->getPosition() ?? 0;
            if ($posA === $posB) {
                return strnatcmp((string) $a->getTranslation('name'), (string) $b->getTranslation('name'));
            }

            return $posA <=> $posB;
        });
    }

    public function sortByConfig(): void
    {
        foreach ($this->elements as $group) {
            $options = $group->getOptions();
            if (!$options instanceof PropertyGroupOptionCollection) {
                continue;
            }

            $columns = [];
            $entities = [];

            $sortingType = $group->getSortingType();

            foreach ($options->getIterator() as $option) {
                if ($sortingType === PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC) {
                    $columns[] = (string) ($option->getTranslation('name') ?? '');
                } else {
                    $columns[] = (int) ($option->getTranslation('position') ?? $option->getPosition() ?? 0);
                }

                $entities[] = $option;
            }

            array_multisort($columns, \SORT_ASC, \SORT_NATURAL, $entities);

            $sortedOptions = new PropertyGroupOptionCollection();
            // Bypass expected class validation for performance optimization
            $sortedOptions->fillOptions($entities);

            $group->setOptions($sortedOptions);
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
