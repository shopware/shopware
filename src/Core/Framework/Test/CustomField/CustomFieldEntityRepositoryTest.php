<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\CustomField;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CustomFieldEntityRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var int
     */
    private $basicSize = 15;

    /**
     * @var string
     */
    private $basicColor = 'blue';

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('product.repository');
        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testUpdateCustomFields(): void
    {
        $newSize = 22;
        $productId = Uuid::randomHex();

        $this->createProduct($productId);

        $this->createBasicCustomFields($productId);

        $product = $this->getProduct($productId);

        static::assertEquals($this->basicSize, $product->getCustomFields()['swag_backpack_size']);
        static::assertEquals($this->basicColor, $product->getCustomFields()['swag_backpack_color']);

        $this->repository->update(
            [
                ['id' => $productId, 'customFields' => ['swag_backpack_size' => $newSize]],
            ],
            $this->context
        );

        $product = $this->getProduct($productId);

        static::assertEquals($newSize, $product->getCustomFields()['swag_backpack_size']);
        static::assertEquals($this->basicColor, $product->getCustomFields()['swag_backpack_color']);
    }

    public function testNewCustomField(): void
    {
        $productId = Uuid::randomHex();
        $this->createProduct($productId);
        $this->createBasicCustomFields($productId);

        $this->repository->update([[
            'id' => $productId,
            'customFields' => ['swag_backpack_material' => 'canvas'],
        ]], Context::createDefaultContext());

        $product = $this->getProduct($productId);

        static::assertEquals('canvas', $product->getCustomFields()['swag_backpack_material']);
        static::assertEquals($this->basicSize, $product->getCustomFields()['swag_backpack_size']);
        static::assertEquals($this->basicColor, $product->getCustomFields()['swag_backpack_color']);
    }

    private function createProduct(string $productId): void
    {
        $data = [
            'id' => $productId,
            'name' => 'testProduct',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];
        $this->repository->create([$data], $this->context);
    }

    private function getProduct(string $productId): ProductEntity
    {
        return $this->repository
            ->search(new Criteria([$productId]), $this->context)
            ->get($productId);
    }

    private function createBasicCustomFields(string $productId): void
    {
        $this->repository->update([[
            'id' => $productId,
            'customFields' => ['swag_backpack_size' => $this->basicSize, 'swag_backpack_color' => $this->basicColor],
        ]], Context::createDefaultContext());
    }
}
