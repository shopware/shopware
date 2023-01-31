<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal;

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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxDefinition;

/**
 * @internal
 */
class ManyToManyAssociationFieldTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository
     */
    private $productRepository;

    private Context $context;

    /**
     * @var EntityRepository
     */
    private $categoryRepository;

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
}
