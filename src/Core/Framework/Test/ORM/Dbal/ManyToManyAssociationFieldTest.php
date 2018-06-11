<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Dbal;

use Shopware\Core\Content\Category\CategoryRepository;
use Shopware\Core\Content\Product\ProductRepository;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ManyToManyAssociationFieldTest extends KernelTestCase
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var Context
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
