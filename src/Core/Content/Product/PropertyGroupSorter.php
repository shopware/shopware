<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

class PropertyGroupSorter extends AbstractPropertyGroupSorter
{
    public function getDecorated(): AbstractPropertyGroupSorter
    {
        throw new DecorationPatternException(self::class);
    }

    public function sort(PropertyGroupOptionCollection $groupOptionCollection): PropertyGroupCollection
    {
        $sorted = [];

        foreach ($groupOptionCollection as $option) {
            $origin = $option->getGroup();

            if ($origin === null || $origin->getVisibleOnProductDetailPage() === false) {
                continue;
            }

            $group = clone $origin;

            $groupId = $group->getId();
            if (\array_key_exists($groupId, $sorted)) {
                \assert($sorted[$groupId]->getOptions() !== null);
                $sorted[$groupId]->getOptions()->add($option);

                continue;
            }

            if ($group->getOptions() === null) {
                $group->setOptions(new PropertyGroupOptionCollection());
            }

            \assert($group->getOptions() !== null);
            $group->getOptions()->add($option);

            $sorted[$groupId] = $group;
        }

        $collection = new PropertyGroupCollection($sorted);
        $collection->sortByPositions();
        $collection->sortByConfig();

        return $collection;
    }
}
