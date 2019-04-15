<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class AvailableStockTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('product.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testCalculatesAvailableStock(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'stock' => 10,
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'asd'],
            ],
        ];

        $this->repository->create([$data], $this->context);

        /** @var ProductEntity $product */
        $product = $this->repository->search(new Criteria([$id]), $this->context)->get($id);

        $this->assertSame(10, $product->getAvailableStock());
    }
}