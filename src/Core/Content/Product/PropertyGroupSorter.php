<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Content\Property\PropertyPartialNormalizer;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
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
     * @deprecated tag:v6.8.0 Use sortUsingLocaleCode() instead.
     * Starting with v6.8.0, the method will be required to have a locale code parameter. This method will be removed.
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

            $groupId = $origin->get('id');

            if (\array_key_exists($groupId, $sorted)) {
                $existingGroup = $sorted[$groupId];
                \assert($existingGroup->getOptions() instanceof PropertyGroupOptionCollection);
                $existingGroup->getOptions()->fillOptions([$option]);
                continue;
            }

            $group = PropertyPartialNormalizer::normalizeGroup($origin);
            if ($group->getOptions() === null) {
                $group->setOptions(new PropertyGroupOptionCollection());
            }

            \assert($group->getOptions() instanceof PropertyGroupOptionCollection);
            $group->getOptions()->fillOptions([$option]);

            $sorted[$groupId] = $group;
        }

        /** @phpstan-ignore argument.type (Partial loading is broken here. will be fixed with https://github.com/shopware/shopware/pull/15240) */
        $collection = new PropertyGroupCollection($sorted);
        $collection->sortByPositions();
        $collection->sortByConfig($localeCode);

        return $collection;
    }
}
