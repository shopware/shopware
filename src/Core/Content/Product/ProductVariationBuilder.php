<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('inventory')]
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
            $aGroup = $a->get('group');
            $bGroup = $b->get('group');
            if (!$aGroup instanceof Entity || !$bGroup instanceof Entity) {
                return $a->get('groupId') <=> $b->get('groupId');
            }

            if ($aGroup->get('position') === $bGroup->get('position')) {
                return $aGroup->getTranslation('name') <=> $bGroup->getTranslation('name');
            }

            return $aGroup->get('position') <=> $bGroup->get('position');
        });

        // fallback - simply take all option names unordered
        $names = array_map(static function (Entity $option) {
            if (!$option->get('group') instanceof Entity) {
                return [];
            }

            return [
                'group' => $option->get('group')->getTranslation('name'),
                'option' => $option->getTranslation('name'),
            ];
        }, $options);

        $product->assign([
            'variation' => \array_values(\array_filter($names)),
        ]);
    }
}
