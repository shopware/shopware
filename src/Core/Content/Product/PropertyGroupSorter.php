<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
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
        $localeCode = \func_num_args() === 2 ? func_get_arg(1) : '';

        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.8.0.0', 'sortUsingLocaleCode()')
        );

        return $this->sortUsingLocaleCode($options, $localeCode);
    }

    public function sortUsingLocaleCode(EntityCollection $options, string $localeCode): PropertyGroupCollection
    {
        $sorted = [];

        foreach ($options as $option) {
            $origin = $option->get('group');

            if (!$origin instanceof Entity || $origin->get('visibleOnProductDetailPage') === false) {
                continue;
            }

            $group = clone $origin;

            $groupId = $group->get('id');
            if (\array_key_exists($groupId, $sorted)) {
                $optionCollection = $sorted[$groupId]->get('options');
                \assert($optionCollection instanceof PropertyGroupOptionCollection);
                $optionCollection->fillOptions([$option]);

                continue;
            }

            if (!$group->get('options') instanceof EntityCollection) {
                $group->assign([
                    'options' => new PropertyGroupOptionCollection(),
                ]);
            }

            $optionCollection = $group->get('options');
            \assert($optionCollection instanceof PropertyGroupOptionCollection);
            $optionCollection->fillOptions([$option]);

            $sorted[$groupId] = $group;
        }

        /** @phpstan-ignore argument.type (Partial loading is broken here. will be fixed with https://github.com/shopware/shopware/pull/15240) */
        $collection = new PropertyGroupCollection($sorted);
        $collection->sortByPositions();
        $collection->sortByConfig();

        return $collection;
    }
}
