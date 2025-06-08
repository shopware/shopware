<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms\ProductSlider;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
abstract class AbstractProductSliderProcessor
{
    protected const PRODUCT_SLIDER_ENTITY_FALLBACK = 'product-slider-entity-fallback';

    abstract public function getDecorated(): AbstractProductSliderProcessor;

    abstract public function getSource(): string;

    abstract public function collect(CmsSlotEntity $slot, FieldConfigCollection $config, ResolverContext $resolverContext): ?CriteriaCollection;

    abstract public function enrich(CmsSlotEntity $slot, ElementDataCollection $result, ResolverContext $resolverContext): void;

    protected function filterOutOutOfStockHiddenCloseoutProducts(ProductCollection $products): ProductCollection
    {
        return $products->filter(function (ProductEntity $product) {
            if ($product->getIsCloseout() && $product->getStock() <= 0) {
                return false;
            }

            return true;
        });
    }
}
