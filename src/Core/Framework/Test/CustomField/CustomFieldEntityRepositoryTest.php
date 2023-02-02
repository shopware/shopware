<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\CustomField;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class CustomFieldEntityRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository
     */
    private $repository;

    private Context $context;

    private int $basicSize = 15;

    private string $basicColor = 'blue';

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('product.repository');
        $this->context = Context::createDefaultContext();
    }

    /**
     * NEXT-16212 - This test sometimes triggers a "SQLSTATE[HY000]: General error: 2006 MySQL server has gone away" error
     *
     * @group quarantined
     */
    public function testUpdateCustomFields(): void
    {
        $newSize = 22;
        $productId = Uuid::randomHex();

        $this->createProduct($productId);

        $this->createBasicCustomFields($productId);

        $product = $this->getProduct($productId);

        $customFields = $product->getCustomFields();
        static::assertIsArray($customFields);

        static::assertEquals($this->basicSize, $customFields['swag_backpack_size']);
        static::assertEquals($this->basicColor, $customFields['swag_backpack_color']);

        $this->repository->update(
            [
                ['id' => $productId, 'customFields' => ['swag_backpack_size' => $newSize]],
            ],
            $this->context
        );

        $product = $this->getProduct($productId);
        $customFields = $product->getCustomFields();
        static::assertIsArray($customFields);

        static::assertEquals($newSize, $customFields['swag_backpack_size']);
        static::assertEquals($this->basicColor, $customFields['swag_backpack_color']);
    }

    public function testNewCustomField(): void
    {
        static::markTestSkipped('NEXT-16212 - This test sometimes triggers a "SQLSTATE[HY000]: General error: 2006 MySQL server has gone away" error');

        $productId = Uuid::randomHex();
        $this->createProduct($productId);
        $this->createBasicCustomFields($productId);

        $this->repository->update([[
            'id' => $productId,
            'customFields' => ['swag_backpack_material' => 'canvas'],
        ]], Context::createDefaultContext());

        $product = $this->getProduct($productId);
        $customFields = $product->getCustomFields();
        static::assertIsArray($customFields);

        static::assertEquals('canvas', $customFields['swag_backpack_material']);
        static::assertEquals($this->basicSize, $customFields['swag_backpack_size']);
        static::assertEquals($this->basicColor, $customFields['swag_backpack_color']);
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
