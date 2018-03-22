<?php declare(strict_types=1);

namespace Shopware\Api\Test\Entity\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Category\Repository\CategoryRepository;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\Uuid;
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
        $parentId = Uuid::uuid4()->getHex();
        $categories = [
            ['id' => $parentId, 'name' => 'master'],
        ];

        for ($i = 0; $i < 400; ++$i) {
            $categories[] = [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'test' . $i,
                'parentId' => $parentId,
            ];
        }

        $this->container->get(CategoryRepository::class)
            ->create($categories, ShopContext::createDefaultContext());

        $mapping = array_map(function (array $category) {
            return ['id' => $category['id']];
        }, $categories);

        $id = Uuid::uuid4()->getHex();
        $product = [
            'id' => $id,
            'name' => 'Test product',
            'price' => ['gross' => 100, 'net' => 99],
            'categories' => $mapping,
            'manufacturer' => ['name' => 'Test'],
            'tax' => ['name' => 'test', 'rate' => 5],
        ];

        $this->container->get(ProductRepository::class)
            ->create([$product], ShopContext::createDefaultContext());

        $detail = $this->container->get(ProductRepository::class)
            ->readDetail([$id], ShopContext::createDefaultContext());

        $this->assertCount(401, $detail->getAllCategories());
    }
}
