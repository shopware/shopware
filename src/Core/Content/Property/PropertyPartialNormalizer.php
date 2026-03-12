<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Normalizes PartialEntity instances into typed property entities.
 */
#[Package('inventory')]
class PropertyPartialNormalizer
{
    public static function normalizeGroup(PropertyGroupEntity|PartialEntity $entity): PropertyGroupEntity
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

    public static function normalizeOption(PropertyGroupOptionEntity|PartialEntity $entity): PropertyGroupOptionEntity
    {
        if ($entity instanceof PropertyGroupOptionEntity) {
            return $entity;
        }

        $normalized = new PropertyGroupOptionEntity();
        $normalized->setId((string) $entity->get('id'));

        $groupId = $entity->get('groupId');
        if (\is_string($groupId) && $groupId !== '') {
            $normalized->setGroupId($groupId);
        }

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

        $group = $entity->get('group');
        if ($group instanceof PropertyGroupEntity || $group instanceof PartialEntity) {
            $normalized->setGroup(self::normalizeGroup($group));
        }

        if (!\is_string($groupId) && $group instanceof Entity) {
            $groupId = $group->get('id');
            if (\is_string($groupId) && $groupId !== '') {
                $normalized->setGroupId($groupId);
            }
        }

        return $normalized;
    }
}
