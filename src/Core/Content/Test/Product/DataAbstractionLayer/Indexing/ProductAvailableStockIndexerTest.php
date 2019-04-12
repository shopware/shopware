<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\StorefrontFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductAvailableStockIndexerTest extends TestCase
{
    use StorefrontFunctionalTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testRecalculateIsExecutedOnProductCreation(): void
    {
        $stock = 100;

        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $this->productRepository->create(
            [
                [
                    'id' => $productId,
                    'stock' => $stock,
                    'name' => 'Test',
                    'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                    'manufacturer' => ['id' => Uuid::randomHex(), 'name' => 'test'],
                    'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 17, 'name' => 'with id'],
                ],
            ],
            $context
        );
        $product = $this->productRepository->search(new Criteria([$productId]), $context)->getEntities()->first();

        static::assertEquals($stock, $product->getStock());
        static::assertEquals($stock, $product->getAvailableStock());
    }
}
