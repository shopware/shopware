<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\DataResolver\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageSliderItemStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageSliderStruct;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\PropertyNotFoundException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\Framework\DataAbstractionLayer\TestEntityDefinition;
use Shopware\Tests\Unit\Core\Content\Cms\DataResolver\Element\Fixtures\TestCmsElementResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AbstractCmsElementResolver::class)]
class AbstractCmsElementResolverTest extends TestCase
{
    private DefinitionInstanceRegistry&MockObject $registry;

    private TestEntityDefinition $definition;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->definition = new TestEntityDefinition();
        $this->definition->compile($this->registry);
    }

    public function testResolveEmptyValue(): void
    {
        $actual = (new TestCmsElementResolver())->runResolveEntityValue(null, 'parent.manufacturer.description');

        static::assertNull($actual);
    }

    public function testResolveNestedEntityNullValue(): void
    {
        $product = new ProductEntity();
        $product->setUniqueIdentifier('product');

        $actual = (new TestCmsElementResolver())->runResolveEntityValue($product, 'parent.manufacturer.description');

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

        $actual = (new TestCmsElementResolver())->runResolveEntityValue($childProduct, 'parent.manufacturer.description');

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

        $actual = (new TestCmsElementResolver())->runResolveEntityValue($product, 'manufacturer.description');

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
        $actualTranslation = $cmsElementResolver->runResolveEntityValue($childProduct, 'parent.manufacturer.description');
        $actualName = $cmsElementResolver->runResolveEntityValue($childProduct, 'parent.manufacturer.name');

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

        $actual = (new TestCmsElementResolver())->runResolveEntityValue($entity, 'imageSlider.sliderItems.0.url');

        static::assertSame($expected, $actual);
    }

    public function testResolveInvalidArgumentException(): void
    {
        $product = new ProductEntity();
        $product->setUniqueIdentifier('product');

        $this->expectException(PropertyNotFoundException::class);
        $this->expectExceptionMessage('Property "doesntActuallyExist" does not exist in entity "Shopware\Core\Content\Product\ProductEntity".');
        (new TestCmsElementResolver())->runResolveEntityValue($product, 'that.doesntActuallyExist');
    }

    public function testResolveEntityValueToString(): void
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setUpdatedAt(new \DateTimeImmutable());

        $product = new ProductEntity();
        $product->setUniqueIdentifier('product');
        $product->setManufacturer($manufacturer);

        $childProduct = new ProductEntity();
        $childProduct->setUniqueIdentifier('childProduct');
        $childProduct->setParent($product);

        $context = $this->getEntityResolverContext($product);

        $actual = (new TestCmsElementResolver())->runResolveEntityValueToString(
            $childProduct,
            'parent.manufacturer.updatedAt',
            $context
        );

        try {
            $actual = new \DateTimeImmutable($actual);
            static::assertIsInt($actual->getTimestamp());
        } catch (\Exception) {
            static::fail('Entity value is not a valid date time');
        }
    }

    private function getEntityResolverContext(
        ?ProductEntity $product = null,
        ?EntityDefinition $definition = null
    ): EntityResolverContext {
        if (!$product) {
            $product = new ProductEntity();
            $product->setUniqueIdentifier('product');
        }

        return new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            $definition ?? $this->definition,
            $product
        );
    }
}
