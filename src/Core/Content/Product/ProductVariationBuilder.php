<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use function array_values;

/**
 * @package inventory
 */
class ProductVariationBuilder extends AbstractProductVariationBuilder
{
    public function getDecorated(): AbstractProductVariationBuilder
    {
        throw new DecorationPatternException(self::class);
    }

    public function build(Entity $product): void
    {
        /** @var EntityCollection<Entity>|null $options */
        $options = $product->get('options');
        if ($options === null) {
            $product->assign([
                'variation' => [],
            ]);

            return;
        }

        $options = $options->getElements();

        uasort($options, static function (Entity $a, Entity $b) {
            if ($a->get('group') === null || $b->get('group') === null) {
                return $a->get('groupId') <=> $b->get('groupId');
            }

            if ($a->get('group')->get('position') === $b->get('group')->get('position')) {
                return $a->get('group')->getTranslation('name') <=> $b->get('group')->getTranslation('name');
            }

            return $a->get('group')->get('position') <=> $b->get('group')->get('position');
        });

        // fallback - simply take all option names unordered
        $names = array_map(static function (PropertyGroupOptionEntity $option) {
            if (!$option->get('group')) {
                return [];
            }

            return [
                'group' => $option->get('group')->getTranslation('name'),
                'option' => $option->getTranslation('name'),
            ];
        }, $options);

        $product->assign([
            'variation' => array_values($names),
        ]);
    }
}
