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

    /**
     * @deprecated tag:v6.8.0 - reason:new-optional-parameter - parameter $localeCode will be added
     */
    public function sort(EntityCollection $options /* ?string $localeCode = null */): PropertyGroupCollection
    {
        $localeCode = \func_num_args() === 2 ? func_get_arg(1) : null;

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
        $collection->sortByConfig($localeCode);

        return $collection;
    }
}
