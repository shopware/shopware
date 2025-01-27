<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cms\Utils\ProductSlider;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
trait ProductSliderUnitTrait
{
    private function getProducts(): ProductCollection
    {
        $product = (new ProductEntity())->assign([
            'id' => 'product-1',
            '_uniqueIdentifier' => 'product-1',
            'isCloseout' => false,
            'stock' => 12,
        ]);

        $product2 = (new ProductEntity())->assign([
            'id' => 'product-2',
            '_uniqueIdentifier' => 'product-2',
            'isCloseout' => true,
            'stock' => 0,
        ]);

        return new ProductCollection([$product, $product2]);
    }

    private function getEntitySearchResult(ProductCollection $products): EntitySearchResult
    {
        return new EntitySearchResult(
            'product',
            $products->count(),
            $products,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    private function getSlot(): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-slider');
        $slot->setFieldConfig($this->config);

        return $slot;
    }

    private function getResolverContext(): ResolverContext
    {
        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        return new ResolverContext($context, $request);
    }
}
