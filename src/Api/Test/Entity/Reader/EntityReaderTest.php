<?php declare(strict_types=1);

namespace Shopware\Api\Test\Entity\Reader;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Category\Repository\CategoryRepository;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityReaderTest extends KernelTestCase
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp()
    {
        self::bootKernel();
        parent::setUp();
        $this->container = self::$kernel->getContainer();

        $this->connection = $this->container->get(Connection::class);
        $this->connection->beginTransaction();
        $this->connection->executeUpdate('DELETE FROM product');
    }

    public function testMaxGroupConcat()
    {
        $parentId = Uuid::uuid4()->toString();
        $categories = [
            ['id' => $parentId, 'name' => 'master'],
        ];

        for ($i = 0; $i < 400; ++$i) {
            $categories[] = [
                'id' => Uuid::uuid4()->toString(),
                'name' => 'test' . $i,
                'parentId' => $parentId,
            ];
        }

        $this->container->get(CategoryRepository::class)
            ->create($categories, TranslationContext::createDefaultContext());

        $mapping = array_map(function (array $category) {
            return ['id' => $category['id']];
        }, $categories);

        $id = Uuid::uuid4()->toString();
        $product = [
            'id' => $id,
            'name' => 'Test product',
            'price' => 100,
            'categories' => $mapping,
            'manufacturer' => ['name' => 'Test'],
            'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9',
        ];

        $this->container->get(ProductRepository::class)
            ->create([$product], TranslationContext::createDefaultContext());

        $detail = $this->container->get(ProductRepository::class)
            ->readDetail([$id], TranslationContext::createDefaultContext());

        $this->assertCount(401, $detail->getAllCategories());
    }
}
