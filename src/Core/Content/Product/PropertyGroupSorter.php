<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('inventory')]
class PropertyGroupSorter extends AbstractPropertyGroupSorter
{
    public function getDecorated(): AbstractPropertyGroupSorter
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @deprecated tag:v6.8.0 Use sortUsingLocaleCode() instead.
     * Starting with v6.8.0, the method will be required to have a locale code parameter. This method will be removed.
     */
    public function sort(EntityCollection $options): PropertyGroupCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.8.0.0', 'sortUsingLocaleCode()')
        );

        return $this->sortUsingLocaleCode($options, '');
    }

    public function sortUsingLocaleCode(EntityCollection $options, string $localeCode): PropertyGroupCollection
    {
        /** @var array<string, PropertyGroupEntity> $sorted */
        $sorted = [];

        foreach ($options as $option) {
            $origin = $option->get('group');

            if (!$origin instanceof Entity) {
                continue;
            }

            if ($origin->get('visibleOnProductDetailPage') === false) {
                continue;
            }

            $groupId = $origin->get('id');
            if (\array_key_exists($groupId, $sorted)) {
                $optionCollection = $sorted[$groupId]->get('options');
                \assert($optionCollection instanceof PropertyGroupOptionCollection);
                $optionCollection->fillOptions([$option]);

                continue;
            }

            $group = $this->normalizeGroup($origin);
            $group->assign([
                'options' => new PropertyGroupOptionCollection(),
            ]);

            $optionCollection = $group->get('options');
            \assert($optionCollection instanceof PropertyGroupOptionCollection);
            $optionCollection->fillOptions([$option]);

            $sorted[$groupId] = $group;
        }

        /** @phpstan-ignore argument.type (Partial loading is broken here. will be fixed with https://github.com/shopware/shopware/pull/15240) */
        $collection = new PropertyGroupCollection($sorted);
        $collection->sortByPositions();
        $collection->sortByConfig($localeCode);

        return $collection;
    }

    private function normalizeGroup(Entity $origin): PropertyGroupEntity
    {
        if ($origin instanceof PropertyGroupEntity) {
            return clone $origin;
        }

        $group = new PropertyGroupEntity();
        $group->setId((string) $origin->get('id'));
        $group->setTranslated((array) ($origin->get('translated') ?? []));

        $name = $origin->get('name');
        if (\is_string($name)) {
            $group->setName($name);
        }

        $description = $origin->get('description');
        if (\is_string($description)) {
            $group->setDescription($description);
        }

        $position = $origin->get('position');
        if ($position !== null) {
            $group->setPosition((int) $position);
        }

        $displayType = $origin->get('displayType');
        $group->setDisplayType(\is_string($displayType) && $displayType !== '' ? $displayType : PropertyGroupDefinition::DISPLAY_TYPE_TEXT);

        $sortingType = $origin->get('sortingType');
        $group->setSortingType(\is_string($sortingType) && $sortingType !== '' ? $sortingType : PropertyGroupDefinition::SORTING_TYPE_POSITION);

        $group->setVisibleOnProductDetailPage((bool) ($origin->get('visibleOnProductDetailPage') ?? false));

        return $group;
    }
}
