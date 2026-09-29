<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
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
     * @deprecated tag:v6.8.0 - Will be removed in v6.8.0. Use sortUsingLocaleCode() instead.
     */
    public function sort(EntityCollection $options): PropertyGroupCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.8.0.0', 'sortUsingLocaleCode()')
        );

        return $this->sortUsingLocaleCode($options, 'en_GB');
    }

    public function sortUsingLocaleCode(EntityCollection $options, string $localeCode): PropertyGroupCollection
    {
        /** @var array<string, PropertyGroupEntity> $sorted */
        $sorted = [];

        foreach ($options as $option) {
            $origin = $option->get('group');
            if (!$origin instanceof PropertyGroupEntity && !$origin instanceof PartialEntity) {
                continue;
            }

            if ($origin->get('visibleOnProductDetailPage') === false) {
                continue;
            }

            $groupId = (string) $origin->get('id');

            if (!\array_key_exists($groupId, $sorted)) {
                $group = $this->normalizeGroup($origin);
                $group->setOptions(new PropertyGroupOptionCollection());
                $sorted[$groupId] = $group;
            }

            $normalizedOption = $this->normalizeOption($option);
            $normalizedOption->setGroupId($groupId);

            \assert($sorted[$groupId]->getOptions() instanceof PropertyGroupOptionCollection);

            $sorted[$groupId]->getOptions()->add($normalizedOption);
        }

        $collection = new PropertyGroupCollection($sorted);
        $collection->sortByPositions();
        $collection->sortByConfig($localeCode);

        return $collection;
    }

    private function normalizeGroup(PropertyGroupEntity|PartialEntity $entity): PropertyGroupEntity
    {
        if ($entity instanceof PropertyGroupEntity) {
            return clone $entity;
        }

        $group = new PropertyGroupEntity();
        $group->setId((string) $entity->get('id'));
        $group->setTranslated((array) ($entity->get('translated') ?? []));

        $name = $entity->get('name');
        if (\is_string($name)) {
            $group->setName($name);
        }

        $description = $entity->get('description');
        if (\is_string($description)) {
            $group->setDescription($description);
        }

        $position = $entity->get('position');
        if ($position !== null) {
            $group->setPosition((int) $position);
        }

        $displayType = $entity->get('displayType');
        $group->setDisplayType(\is_string($displayType) && $displayType !== '' ? $displayType : PropertyGroupDefinition::DISPLAY_TYPE_TEXT);

        $sortingType = $entity->get('sortingType');
        $group->setSortingType(\is_string($sortingType) && $sortingType !== '' ? $sortingType : PropertyGroupDefinition::SORTING_TYPE_POSITION);

        $group->setVisibleOnProductDetailPage((bool) ($entity->get('visibleOnProductDetailPage') ?? false));

        return $group;
    }

    private function normalizeOption(PropertyGroupOptionEntity|PartialEntity $entity): PropertyGroupOptionEntity
    {
        if ($entity instanceof PropertyGroupOptionEntity) {
            return $entity;
        }

        $normalized = new PropertyGroupOptionEntity();
        $normalized->setId((string) $entity->get('id'));

        $name = $entity->get('name');
        if (\is_string($name)) {
            $normalized->setName($name);
        }

        $position = $entity->get('position');
        if ($position !== null) {
            $normalized->setPosition((int) $position);
        }

        $colorHexCode = $entity->get('colorHexCode');
        if (\is_string($colorHexCode)) {
            $normalized->setColorHexCode($colorHexCode);
        }

        $translated = $entity->get('translated');
        if (\is_array($translated)) {
            $normalized->setTranslated($translated);
        }

        $combinable = $entity->get('combinable');
        if (\is_bool($combinable)) {
            $normalized->setCombinable($combinable);
        }

        return $normalized;
    }
}
