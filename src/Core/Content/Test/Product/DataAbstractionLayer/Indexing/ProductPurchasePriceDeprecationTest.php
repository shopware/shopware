<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/** @deprecated tag:v6.4.0 will be removed in v6.4.0 */
class ProductPurchasePriceDeprecationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    public function tearDown(): void
    {
        $this->setBlueGreen(true);
    }

    public function testInsertDeprecatedBlueGreen(): void
    {
        $this->setBlueGreen(true);
        $this->assertInsertDeprecated();
    }

    public function testInsertNewBlueGreen(): void
    {
        $this->setBlueGreen(true);
        $this->assertInsertNew();
    }

    public function testUpdateDeprecatedBlueGreen(): void
    {
        $this->setBlueGreen(true);
        $this->assertUpdateDeprecated();
    }

    public function testUpdateNewBlueGreen(): void
    {
        $this->setBlueGreen(true);
        $this->assertUpdateNew();
    }

    public function testInsertDeprecated(): void
    {
        $this->setBlueGreen(false);
        $this->assertInsertDeprecated();
    }

    public function testInsertNew(): void
    {
        $this->setBlueGreen(false);
        $this->assertInsertNew();
    }

    public function testUpdateDeprecated(): void
    {
        $this->setBlueGreen(false);
        $this->assertUpdateDeprecated();
    }

    public function testUpdateNew(): void
    {
        $this->setBlueGreen(false);
        $this->assertUpdateNew();
    }

    public function testResetInheritance(): void
    {
        $this->setBlueGreen(false);
        $this->assertResetInheritance();
    }

    public function testResetInheritanceBlueGreen(): void
    {
        $this->setBlueGreen(true);
        $this->assertResetInheritance();
    }

    public function assertInsertDeprecated(): void
    {
        $product = [
            'id' => Uuid::randomHex(),
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'tax' => [
                'id' => Uuid::randomHex(),
                'taxRate' => 13,
                'name' => 'green',
            ],
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'purchasePrice' => 2345.5432,
            'purchasePrices' => null,
        ];

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('product.repository');
        $repository->create([$product], Context::createDefaultContext());

        $criteria = new Criteria([$product['id']]);

        /** @var ProductEntity $productEntity */
        $productEntity = $repository->search($criteria, Context::createDefaultContext())->first();

        $actualPurchasePrices = $productEntity->getPurchasePrices();
        static::assertNotNull($actualPurchasePrices);
        static::assertCount(1, $actualPurchasePrices);

        $expectedPurchasePrice = $product['purchasePrice'];

        static::assertSame($expectedPurchasePrice, $actualPurchasePrices->first()->getGross());
        static::assertSame(Defaults::CURRENCY, $actualPurchasePrices->first()->getCurrencyId());
    }

    public function assertInsertNew(): void
    {
        $product = $this->insertProduct();

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('product.repository');
        $criteria = new Criteria([$product['id']]);

        /** @var ProductEntity $productEntity */
        $productEntity = $repository->search($criteria, Context::createDefaultContext())->first();

        $purchasePrices = $productEntity->getPurchasePrices();
        static::assertNotNull($purchasePrices);
        static::assertCount(1, $purchasePrices);

        $expectedPurchasePrices = $product['purchasePrices'][0];

        static::assertCount(1, $purchasePrices);

        $actualPurchasePrices = $purchasePrices->get($expectedPurchasePrices['currencyId']);

        static::assertInstanceOf(Price::class, $actualPurchasePrices);

        static::assertNotNull($actualPurchasePrices);
        static::assertSame($expectedPurchasePrices['gross'], $actualPurchasePrices->getGross());
        static::assertSame($expectedPurchasePrices['currencyId'], $actualPurchasePrices->getCurrencyId());

        static::assertNotNull($productEntity->getPurchasePrice());
        static::assertSame($expectedPurchasePrices['gross'], $productEntity->getPurchasePrice());
    }

    public function assertUpdateDeprecated(): void
    {
        $product = $this->insertProduct();

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('product.repository');

        $repository->update(
            [[
                'id' => $product['id'],
                'purchasePrice' => 3456.5432,
            ]],
            Context::createDefaultContext()
        );

        $criteria = new Criteria([$product['id']]);

        /** @var ProductEntity $productEntity */
        $productEntity = $repository->search($criteria, Context::createDefaultContext())->first();

        $purchasePrices = $productEntity->getPurchasePrices();
        static::assertNotNull($purchasePrices);
        static::assertCount(1, $purchasePrices);

        $expectedPurchasePriceGross = 3456.5432;

        static::assertCount(1, $purchasePrices);

        $actualPurchasePrices = $purchasePrices->get(Defaults::CURRENCY);

        static::assertInstanceOf(Price::class, $actualPurchasePrices);

        static::assertNotNull($actualPurchasePrices);
        static::assertSame($expectedPurchasePriceGross, $actualPurchasePrices->getGross());
        static::assertSame(Defaults::CURRENCY, $actualPurchasePrices->getCurrencyId());

        static::assertNotNull($productEntity->getPurchasePrice());
        static::assertSame($expectedPurchasePriceGross, $productEntity->getPurchasePrice());
    }

    public function assertUpdateNew(): void
    {
        $product = $this->insertProduct();

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('product.repository');

        $repository->update(
            [
                [
                    'id' => $product['id'],
                    'purchasePrices' => [
                        [
                            'net' => 2345.4321,
                            'gross' => 3456.5432,
                            'currencyId' => Defaults::CURRENCY,
                            'linked' => false,
                        ],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );

        $criteria = new Criteria([$product['id']]);

        /** @var ProductEntity $productEntity */
        $productEntity = $repository->search($criteria, Context::createDefaultContext())->first();

        $purchasePrices = $productEntity->getPurchasePrices();
        static::assertNotNull($purchasePrices);
        static::assertCount(1, $purchasePrices);

        static::assertCount(1, $purchasePrices);

        $actualPurchasePrices = $purchasePrices->get(Defaults::CURRENCY);

        static::assertInstanceOf(Price::class, $actualPurchasePrices);

        static::assertNotNull($actualPurchasePrices);
        static::assertSame(3456.5432, $actualPurchasePrices->getGross());
        static::assertSame(Defaults::CURRENCY, $actualPurchasePrices->getCurrencyId());

        static::assertNotNull($productEntity->getPurchasePrice());
        static::assertSame(3456.5432, $productEntity->getPurchasePrice());
    }

    public function assertResetInheritance(): void
    {
        $ids = new IdsCollection();

        $data = [
            [
                'id' => $ids->create('parent'),
                'name' => 'test',
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                ],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'purchasePrices' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                ],
            ],
            [
                'id' => $ids->create('child'),
                'productNumber' => Uuid::randomHex(),
                'parentId' => $ids->get('parent'),
                'stock' => 10,
                'purchasePrices' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 1, 'net' => 1, 'linked' => false],
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create($data, Context::createDefaultContext());

        $update = [
            'id' => $ids->get('child'),
            'purchasePrices' => null,
        ];

        $this->getContainer()->get('product.repository')
            ->update([$update], Context::createDefaultContext());

        $variant = $this->getContainer()
            ->get('product.repository')
            ->search(new Criteria([$ids->get('child')]), Context::createDefaultContext())
            ->first();

        /** @var ProductEntity $variant */
        static::assertInstanceOf(ProductEntity::class, $variant);
        static::assertNull($variant->getPurchasePrices());
    }

    public function testBlueGreen(): void
    {
        $this->setBlueGreen(false);

        $b = $this->getContainer()->getParameter('shopware.deployment.blue_green');
        static::assertFalse($b);

        $this->setBlueGreen(true);
        $c = $this->getContainer()->getParameter('shopware.deployment.blue_green');
        static::assertTrue($c);
    }

    private function setBlueGreen(?bool $enabled): void
    {
        $this->getContainer()->get(Connection::class)->rollBack();

        if ($enabled === null) {
            unset($_ENV['BLUE_GREEN_DEPLOYMENT']);
        } else {
            $_ENV['BLUE_GREEN_DEPLOYMENT'] = $enabled ? '1' : '0';
        }

        // reload env
        KernelLifecycleManager::bootKernel();

        $this->getContainer()->get(Connection::class)->beginTransaction();
        if ($enabled !== null) {
            $this->getContainer()->get(Connection::class)->executeUpdate('SET @TRIGGER_DISABLED = ' . ($enabled ? '0' : '1'));
        }
    }

    private function getShippingMethod(string $priceId): array
    {
        $shippingMethod = [
            'id' => Uuid::randomHex(),
            'name' => 'test',
            'active' => true,
            'availabilityRuleId' => $this->getAvailableShippingMethod()->getAvailabilityRuleId(),
            'deliveryTime' => [
                'id' => Uuid::randomHex(),
                'name' => 'test',
                'min' => 1,
                'max' => 3,
                'unit' => 'days',
            ],
            'prices' => [
                [
                    'id' => $priceId,
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 12.37,
                            'gross' => 13.37,
                            'linked' => false,
                        ],
                    ],
                ],
            ],
        ];

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('shipping_method.repository');
        $repository->create([$shippingMethod], Context::createDefaultContext());

        return $shippingMethod;
    }

    private function insertProduct(): array
    {
        $product = [
            'id' => Uuid::randomHex(),
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'tax' => [
                'id' => Uuid::randomHex(),
                'taxRate' => 13,
                'name' => 'green',
            ],
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'purchasePrices' => [
                [
                    'net' => 1234.4321,
                    'gross' => 2345.5432,
                    'currencyId' => Defaults::CURRENCY,
                    'linked' => false,
                ],
            ],
        ];

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('product.repository');
        $repository->create([$product], Context::createDefaultContext());

        return $product;
    }
}
