<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\DataAbstractionLayer\Indexing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class AvailableStockIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->getContainer()->get('product.repository');
    }

    public function testAvailableOnInsert()
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'Test',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
        ];

        $context = Context::createDefaultContext();
        $this->repository->create([$product], $context);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $context)
            ->get($id);

        static::assertTrue($product->getAvailable());
        static::assertSame(10, $product->getAvailableStock());
    }

    public function testAvailableWithoutStock()
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 0,
            'isCloseout' => true,
            'name' => 'Test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
        ];

        $context = Context::createDefaultContext();
        $this->repository->create([$product], $context);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $context)
            ->get($id);

        static::assertTrue($product->getIsCloseout());
        static::assertFalse($product->getAvailable());
        static::assertSame(0, $product->getAvailableStock());
    }

    public function testAvailableAfterUpdate()
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'Test',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
        ];

        $context = Context::createDefaultContext();
        $this->repository->create([$product], $context);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $context)
            ->get($id);

        static::assertTrue($product->getAvailable());
        static::assertSame(10, $product->getAvailableStock());

        $this->repository->update([['id' => $id, 'stock' => 0]], $context);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $context)
            ->get($id);

        static::assertTrue($product->getIsCloseout());
        static::assertFalse($product->getAvailable());
        static::assertSame(0, $product->getAvailableStock());
    }
}
