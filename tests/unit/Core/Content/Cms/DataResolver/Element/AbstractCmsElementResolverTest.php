<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\DataResolver\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageSliderItemStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageSliderStruct;
use Shopware\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\Framework\DataAbstractionLayer\TestEntityDefinition;
use Shopware\Tests\Unit\Core\Content\Cms\DataResolver\Element\Fixtures\TestCmsElementResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('buyers-experience')]
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
        $cmsElementResolver = new TestCmsElementResolver();
        $actual = $cmsElementResolver->runResolveEntityValue(null, 'parent.manufacturer.description');

        static::assertNull($actual);
    }

    public function testResolveNestedEntityNullValue(): void
    {
        $product = new ProductEntity();
        $product->setUniqueIdentifier('product');

        $cmsElementResolver = new TestCmsElementResolver();
        $actual = $cmsElementResolver->runResolveEntityValue($product, 'parent.manufacturer.description');

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
        $actual = $cmsElementResolver->runResolveEntityValue($childProduct, 'parent.manufacturer.description');

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
        $actual = $cmsElementResolver->runResolveEntityValue($product, 'manufacturer.description');

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

        $cmsElementResolver = new TestCmsElementResolver();
        $actual = $cmsElementResolver->runResolveEntityValue($entity, 'imageSlider.sliderItems.0.url');

        static::assertSame($expected, $actual);
    }

    public function testResolveInvalidArgumentException(): void
    {
        $product = new ProductEntity();
        $product->setUniqueIdentifier('product');

        $cmsElementResolver = new TestCmsElementResolver();

        $this->expectException(\InvalidArgumentException::class);
        $cmsElementResolver->runResolveEntityValue($product, 'that.doesntActuallyExist');
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

        $cmsElementResolver = new TestCmsElementResolver();
        $actual = $cmsElementResolver->runResolveEntityValueToString(
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

    public function testResolveDefinitionField(): void
    {
        $resolver = new TestCmsElementResolver();
        $actual = $resolver->runResolveDefinitionField($this->definition, 'id');
        static::assertInstanceOf(IdField::class, $actual);
    }

    public function testResolveCriteriaForLazyLoadedRelationsReturnsNullWithoutAssociations(): void
    {
        $context = $this->getEntityResolverContext();
        $config = new FieldConfig('config', FieldConfig::SOURCE_DEFAULT, 'id');

        $resolver = new TestCmsElementResolver();
        $actual = $resolver->runResolveCriteriaForLazyLoadedRelations($context, $config);
        static::assertNull($actual);
    }

    public function testResolveCriteriaForLazyLoadedRelationsHandlesAssociations(): void
    {
        $associationField = $this->getMockBuilder(OneToManyAssociationField::class)
            ->setConstructorArgs(['downloads', ProductDownloadDefinition::class, 'product_id'])
            ->onlyMethods(['getReferenceDefinition'])
            ->getMock();

        $definition = $this->getMockBuilder(ProductDefinition::class)
            ->onlyMethods(['defineFields'])
            ->getMock();

        $referenceDefinition = $this->getReferenceDefinition();

        $associationField->method('getReferenceDefinition')->willReturn($referenceDefinition);
        $associationField->compile($this->registry);

        $definition->method('defineFields')->willReturn(new FieldCollection([$associationField]));
        $definition->compile($this->registry);

        $context = $this->getEntityResolverContext(definition: $definition);

        $config = new FieldConfig('config', FieldConfig::SOURCE_DEFAULT, 'product.downloads');

        $resolver = new TestCmsElementResolver();
        $actual = $resolver->runResolveCriteriaForLazyLoadedRelations($context, $config);
        static::assertInstanceOf(Criteria::class, $actual);

        $filters = $actual->getFilters();
        static::assertCount(1, $filters);

        $filter = array_shift($filters);
        static::assertInstanceOf(EqualsFilter::class, $filter);
        static::assertSame('product_download.downloads.id', $filter->getField());
    }

    private function getReferenceDefinition(): ProductDownloadDefinition&MockObject
    {
        $associationField = $this->getMockBuilder(ManyToOneAssociationField::class)
            ->setConstructorArgs(['downloads', ProductDownloadDefinition::class, 'product_id'])
            ->onlyMethods(['getReferenceDefinition'])
            ->getMock();

        $definition = $this->getMockBuilder(ProductDownloadDefinition::class)
            ->onlyMethods(['defineFields'])
            ->getMock();

        $associationField->method('getReferenceDefinition')->willReturn($definition);
        $associationField->compile($this->registry);

        $definition->method('defineFields')
            ->willReturn(new FieldCollection([$associationField]));

        $definition->compile($this->registry);

        return $definition;
    }

    private function getEntityResolverContext(?ProductEntity $product = null, ?EntityDefinition $definition = null): EntityResolverContext
    {
        if (!$product) {
            $product = new ProductEntity();
            $product->setUniqueIdentifier('product');
        }

        return new EntityResolverContext(
            Generator::createSalesChannelContext(),
            new Request(),
            $definition ?? $this->definition,
            $product
        );
    }
}
