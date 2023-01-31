<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
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
        usort($this->elements, function (Entity $a, Entity $b) {
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
        /** @var Entity $group */
        foreach ($this->elements as $group) {
            $options = $group->get('options');
            if (!$options instanceof EntityCollection) {
                continue;
            }

            $options->sort(static function (Entity $a, Entity $b) use ($group) {
                if ($group->get('sortingType') === PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC) {
                    return strnatcmp((string) $a->getTranslation('name'), (string) $b->getTranslation('name'));
                }

                return ($a->getTranslation('position') ?? $a->get('position') ?? 0) <=> ($b->getTranslation('position') ?? $b->get('position') ?? 0);
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
