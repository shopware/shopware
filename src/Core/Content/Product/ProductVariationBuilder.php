<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

class ProductVariationBuilder extends AbstractProductVariationBuilder
{
    public function getDecorated(): AbstractProductVariationBuilder
    {
        throw new DecorationPatternException(self::class);
    }

    public function build(ProductEntity $product): void
    {
        if ($product->getOptions() === null) {
            $product->setVariation([]);

            return;
        }

        $options = $product->getOptions()->getElements();
        uasort($options, function (PropertyGroupOptionEntity $a, PropertyGroupOptionEntity $b) {
            if ($a->getGroup() === null || $b->getGroup() === null) {
                return $a->getGroupId() <=> $b->getGroupId();
            }

            if ($a->getGroup()->getPosition() === $b->getGroup()->getPosition()) {
                return $a->getGroup()->getTranslation('name') <=> $b->getGroup()->getTranslation('name');
            }

            return $a->getGroup()->getPosition() <=> $b->getGroup()->getPosition();
        });

        // fallback - simply take all option names unordered
        $names = array_map(function (PropertyGroupOptionEntity $option) {
            if (!$option->getGroup()) {
                return [];
            }

            return [
                'group' => $option->getGroup()->getTranslation('name'),
                'option' => $option->getTranslation('name'),
            ];
        }, $options);

        $product->setVariation(array_values($names));
    }
}
