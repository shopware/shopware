<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('inventory')]
class PropertyGroupSorter extends AbstractPropertyGroupSorter
{
    public function getDecorated(): AbstractPropertyGroupSorter
    {
        throw new DecorationPatternException(self::class);
    }

    public function sort(EntityCollection $options): PropertyGroupCollection
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
                $groupOptions = $sorted[$groupId]->get('options');
                if ($groupOptions instanceof EntityCollection) {
                    $groupOptions->add($option);
                }

                continue;
            }

            if (!$group->get('options') instanceof EntityCollection) {
                $group->assign([
                    'options' => new PropertyGroupOptionCollection(),
                ]);
            }

            $groupOptions = $group->get('options');
            if ($groupOptions instanceof EntityCollection) {
                $groupOptions->add($option);
            }

            $sorted[$groupId] = $group;
        }

        /** @phpstan-ignore argument.type (Partial loading is broken here. will be fixed with https://github.com/shopware/shopware/pull/15240) */
        $collection = new PropertyGroupCollection($sorted);
        $collection->sortByPositions();
        $collection->sortByConfig();

        return $collection;
    }
}
