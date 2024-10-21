<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxDefinition;

/**
 * @internal
 */
class ManyToManyAssociationFieldTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $productRepository;

    private Context $context;

    private EntityRepository $categoryRepository;

    protected function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->categoryRepository = $this->getContainer()->get('category.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testWriteWithoutData(): void
    {
        $categoryId = Uuid::randomHex();
        $data = [
            'id' => $categoryId,
            'name' => 'test',
        ];

        $this->categoryRepository->create([$data], $this->context);

        $productId = Uuid::randomHex();
        $data = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $categoryId],
            ],
        ];

        $writtenEvent = $this->productRepository->create([$data], $this->context);

        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(TaxDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(ProductManufacturerDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(ProductCategoryDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(ProductManufacturerTranslationDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(ProductDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(ProductTranslationDefinition::ENTITY_NAME));
        static::assertNotNull($writtenEvent->getEventByEntityName(CategoryDefinition::ENTITY_NAME));
        static::assertNull($writtenEvent->getEventByEntityName(CategoryTranslationDefinition::ENTITY_NAME));
    }

    public function testWriteWithData(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'asd'],
            ],
        ];

        $writtenEvent = $this->productRepository->create([$data], $this->context);

        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(TaxDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(CategoryDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(CategoryTranslationDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(ProductManufacturerDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(ProductManufacturerTranslationDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(ProductCategoryDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(ProductDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(ProductTranslationDefinition::ENTITY_NAME));
    }

    public function testReadPartialWithoutAssociationFields(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'test',
            'productNumber' => $id,
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $this->productRepository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addFields(['name']);
        $criteria->addAssociation('properties');

        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertInstanceOf(PartialEntity::class, $product);
        static::assertEquals('test', $product->get('name'));
        static::assertNull($product->get('properties'));
    }

    public function testReadPartialWithAssociationFields(): void
    {
        $id = Uuid::randomHex();
        $propertyId = Uuid::randomHex();
        $groupId = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'test',
            'productNumber' => $id,
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'properties' => [
                [
                    'id' => $propertyId,
                    'name' => 'Propertyname',
                    'group' => [
                        'id' => $groupId,
                        'name' => 'Groupname',
                        'customFields' => [
                            'key' => 'value',
                        ],
                    ],
                ],
            ],
            'cover' => [
                'position' => -1,
                'media' => ['fileName' => 'myFile'],
            ],
        ];

        $this->productRepository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addFields(['productNumber', 'properties.name', 'properties.group.customFields', 'cover.media.fileName']);

        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->first();
        static::assertInstanceOf(PartialEntity::class, $product);

        $properties = $product->get('properties');
        static::assertInstanceOf(EntityCollection::class, $properties);

        $property = $properties->first();
        static::assertInstanceOf(PartialEntity::class, $property);

        $group = $property->get('group');
        static::assertInstanceOf(PartialEntity::class, $group);

        $cover = $product->get('cover');
        static::assertInstanceOf(PartialEntity::class, $cover);

        $media = $cover->get('media');
        static::assertInstanceOf(PartialEntity::class, $media);

        static::assertEquals($id, $product->get('productNumber'));
        static::assertFalse($product->has('name'));
        static::assertFalse($product->has('customFields'));

        static::assertEquals($propertyId, $property->getId());
        static::assertEquals('Propertyname', $property->get('name'));
        static::assertFalse($property->has('customFields'));

        static::assertEquals($groupId, $group->getId());
        static::assertFalse($group->has('name'));
        static::assertEquals('value', $group->get('customFields')['key']);

        static::assertEquals('myFile', $media->get('fileName'));
    }
}
