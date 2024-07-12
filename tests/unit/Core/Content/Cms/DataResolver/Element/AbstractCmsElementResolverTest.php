<?php
declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\DataResolver\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageSliderItemStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageSliderStruct;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\Cms\DataResolver\Element\Fixtures\TestCmsElementResolver;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(AbstractCmsElementResolver::class)]
class AbstractCmsElementResolverTest extends TestCase
{
    public function testResolveEmptyValue(): void
    {
        $cmsElementResolver = new TestCmsElementResolver();
        $actual = $cmsElementResolver->abstractResolveEntityValue(null, 'parent.manufacturer.description');

        static::assertNull($actual);
    }

    public function testResolveNestedEntityNullValue(): void
    {
        $product = new ProductEntity();
        $product->setUniqueIdentifier('product');

        $cmsElementResolver = new TestCmsElementResolver();
        $actual = $cmsElementResolver->abstractResolveEntityValue($product, 'parent.manufacturer.description');

        static::assertNull($actual);
    }

    public function testResolveNestedEntityValue(): void
    {
        $expected = 'manufacturerDescriptionValue';
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setDescription($expected);

        $product = new ProductEntity();
        $product->setUniqueIdentifier('product');
        $product->setManufacturer($manufacturer);

        $childProduct = new ProductEntity();
        $childProduct->setUniqueIdentifier('childProduct');
        $childProduct->setParent($product);

        $cmsElementResolver = new TestCmsElementResolver();
        $actual = $cmsElementResolver->abstractResolveEntityValue($childProduct, 'parent.manufacturer.description');

        static::assertSame($expected, $actual);
    }

    public function testResolveTranslationValue(): void
    {
        $expected = 'Je suis un texte français';
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setTranslated(['description' => $expected]);

        $product = new ProductEntity();
        $product->setUniqueIdentifier('product');
        $product->setManufacturer($manufacturer);

        $cmsElementResolver = new TestCmsElementResolver();
        $actual = $cmsElementResolver->abstractResolveEntityValue($product, 'manufacturer.description');

        static::assertSame($expected, $actual);
    }

    public function testResolveNestedTranslationValue(): void
    {
        $expected = 'Je suis un texte français';

        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setTranslated(['description' => $expected]);

        $product = new ProductEntity();
        $product->setUniqueIdentifier('product');
        $product->setManufacturer($manufacturer);

        $childProduct = new ProductEntity();
        $childProduct->setUniqueIdentifier('childProduct');
        $childProduct->setParent($product);
        $childProduct->setTranslated(['translatedDescription' => 'something went wrong']);

        $cmsElementResolver = new TestCmsElementResolver();
        $actualTranslation = $cmsElementResolver->abstractResolveEntityValue($childProduct, 'parent.manufacturer.description');
        $actualName = $cmsElementResolver->abstractResolveEntityValue($childProduct, 'parent.manufacturer.name');

        static::assertSame($expected, $actualTranslation);
        static::assertNull($actualName);
    }

    public function testResolveNestedStructValue(): void
    {
        $expected = 'workingUrl';

        $sliderItem = new ImageSliderItemStruct();
        $sliderItem->setUrl($expected);

        $imageSliderStruct = new ImageSliderStruct();
        $imageSliderStruct->setSliderItems([$sliderItem]);

        $entity = new Entity();
        $entity->addExtension('imageSlider', $imageSliderStruct);

        $cmsElementResolver = new TestCmsElementResolver();
        $actual = $cmsElementResolver->abstractResolveEntityValue($entity, 'imageSlider.sliderItems.0.url');

        static::assertSame($expected, $actual);
    }

    public function testResolveInvalidArgumentException(): void
    {
        $product = new ProductEntity();
        $product->setUniqueIdentifier('product');

        $cmsElementResolver = new TestCmsElementResolver();

        $this->expectException(\InvalidArgumentException::class);
        $cmsElementResolver->abstractResolveEntityValue($product, 'that.doesntActuallyExist');
    }
}
