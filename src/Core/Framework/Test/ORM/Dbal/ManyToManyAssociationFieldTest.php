<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Dbal;

use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Event\EntityWrittenEvent;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\Tax\TaxDefinition;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ManyToManyAssociationFieldTest extends KernelTestCase
{
    /**
     * @var RepositoryInterface
     */
    private $productRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var RepositoryInterface
     */
    private $categoryRepository;

    protected function setUp()
    {
        self::bootKernel();
        parent::setUp();
        $this->productRepository = self::$container->get('product.repository');
        $this->categoryRepository = self::$container->get('category.repository');
        $this->context = Context::createDefaultContext(Defaults::TENANT_ID);
    }

    public function testWriteWithoutData()
    {
        $categoryId = Uuid::uuid4();
        $data = [
            'id' => $categoryId->getHex(),
            'name' => 'test',
        ];

        $this->categoryRepository->create([$data], $this->context);

        $productId = Uuid::uuid4();
        $data = [
            'id' => $productId->getHex(),
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'rate' => 15],
            'categories' => [
                ['id' => $categoryId->getHex()],
            ],
        ];

        $writtenEvent = $this->productRepository->create([$data], $this->context);

        $this->assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByDefinition(TaxDefinition::class));
        $this->assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByDefinition(ProductManufacturerDefinition::class));
        $this->assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByDefinition(ProductCategoryDefinition::class));
        $this->assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByDefinition(ProductManufacturerTranslationDefinition::class));
        $this->assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByDefinition(ProductDefinition::class));
        $this->assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByDefinition(ProductTranslationDefinition::class));
        $this->assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByDefinition(CategoryDefinition::class));
        $this->assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByDefinition(CategoryTranslationDefinition::class));
    }

    public function testWriteWithData()
    {
        $id = Uuid::uuid4();
        $data = [
            'id' => $id->getHex(),
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'rate' => 15],
            'categories' => [
                ['id' => $id->getHex(), 'name' => 'asd'],
            ],
        ];

        $writtenEvent = $this->productRepository->create([$data], $this->context);

        $this->assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByDefinition(TaxDefinition::class));
        $this->assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByDefinition(CategoryDefinition::class));
        $this->assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByDefinition(CategoryTranslationDefinition::class));
        $this->assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByDefinition(ProductManufacturerDefinition::class));
        $this->assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByDefinition(ProductManufacturerTranslationDefinition::class));
        $this->assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByDefinition(ProductCategoryDefinition::class));
        $this->assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByDefinition(ProductDefinition::class));
        $this->assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByDefinition(ProductTranslationDefinition::class));
    }

    private function containsInstance(string $needle, array $haystack): bool
    {
        foreach ($haystack as $element) {
            if ($element instanceof $needle) {
                return true;
            }
        }

        return false;
    }
}
