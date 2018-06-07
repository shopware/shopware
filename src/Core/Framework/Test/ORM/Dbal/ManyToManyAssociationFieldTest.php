<?php declare(strict_types=1);

namespace Shopware\Framework\Test\ORM\Dbal;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Content\Category\Aggregate\CategoryTranslation\Event\CategoryTranslationWrittenEvent;
use Shopware\Content\Category\CategoryRepository;
use Shopware\Content\Category\Event\CategoryWrittenEvent;
use Shopware\Content\Product\Aggregate\ProductCategory\Event\ProductCategoryWrittenEvent;
use Shopware\Content\Product\Aggregate\ProductManufacturer\Event\ProductManufacturerWrittenEvent;
use Shopware\Content\Product\Aggregate\ProductManufacturerTranslation\Event\ProductManufacturerTranslationWrittenEvent;
use Shopware\Content\Product\Aggregate\ProductTranslation\Event\ProductTranslationWrittenEvent;
use Shopware\Content\Product\Event\ProductWrittenEvent;
use Shopware\Content\Product\ProductRepository;
use Shopware\Defaults;
use Shopware\Framework\Struct\Uuid;
use Shopware\System\Tax\Event\TaxWrittenEvent;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ManyToManyAssociationFieldTest extends KernelTestCase
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var ApplicationContext
     */
    private $context;

    /**
     * @var CategoryRepository
     */
    private $categoryRepository;

    protected function setUp()
    {
        self::bootKernel();
        parent::setUp();
        $this->productRepository = self::$container->get(ProductRepository::class);
        $this->categoryRepository = self::$container->get(CategoryRepository::class);
        $this->context = ApplicationContext::createDefaultContext(Defaults::TENANT_ID);
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
        $events = $writtenEvent->getEvents()->getElements();

        $this->assertTrue($this->containsInstance(TaxWrittenEvent::class, $events));
        $this->assertTrue($this->containsInstance(ProductManufacturerWrittenEvent::class, $events));
        $this->assertTrue($this->containsInstance(ProductCategoryWrittenEvent::class, $events));
        $this->assertTrue($this->containsInstance(ProductManufacturerTranslationWrittenEvent::class, $events));
        $this->assertTrue($this->containsInstance(ProductWrittenEvent::class, $events));
        $this->assertTrue($this->containsInstance(ProductTranslationWrittenEvent::class, $events));
        $this->assertFalse($this->containsInstance(CategoryWrittenEvent::class, $events));
        $this->assertFalse($this->containsInstance(CategoryTranslationWrittenEvent::class, $events));
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
        $events = $writtenEvent->getEvents()->getElements();

        $this->assertTrue($this->containsInstance(TaxWrittenEvent::class, $events));
        $this->assertTrue($this->containsInstance(CategoryWrittenEvent::class, $events));
        $this->assertTrue($this->containsInstance(CategoryTranslationWrittenEvent::class, $events));
        $this->assertTrue($this->containsInstance(ProductManufacturerWrittenEvent::class, $events));
        $this->assertTrue($this->containsInstance(ProductManufacturerTranslationWrittenEvent::class, $events));
        $this->assertTrue($this->containsInstance(ProductCategoryWrittenEvent::class, $events));
        $this->assertTrue($this->containsInstance(ProductWrittenEvent::class, $events));
        $this->assertTrue($this->containsInstance(ProductTranslationWrittenEvent::class, $events));
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
