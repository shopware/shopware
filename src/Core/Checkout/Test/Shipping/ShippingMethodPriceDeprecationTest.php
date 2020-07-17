<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Shipping;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;

class ShippingMethodPriceDeprecationTest extends TestCase
{
    use IntegrationTestBehaviour;

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
        $this->assertUpdateDeprecated();
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
        $this->assertUpdateDeprecated();
    }

    public function assertInsertDeprecated(): void
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
                    'currencyId' => Defaults::CURRENCY,
                    'price' => 1234.4321,
                ],
            ],
        ];

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('shipping_method.repository');
        $repository->create([$shippingMethod], Context::createDefaultContext());

        $criteria = new Criteria([$shippingMethod['id']]);
        $criteria->addAssociation('prices');

        /** @var ShippingMethodEntity $shippingMethodEntity */
        $shippingMethodEntity = $repository->search($criteria, Context::createDefaultContext())->first();

        /** @var ShippingMethodPriceCollection|null $prices */
        $prices = $shippingMethodEntity->getPrices();
        static::assertNotNull($prices);
        static::assertCount(1, $prices);

        $expectedPrice = $shippingMethod['prices'][0];
        $actualPriceCollection = $prices->first()->getCurrencyPrice();

        static::assertInstanceOf(PriceCollection::class, $actualPriceCollection);

        static::assertCount(1, $actualPriceCollection);

        $actualPrice = $actualPriceCollection->get($expectedPrice['currencyId']);

        static::assertNotNull($actualPrice);
        static::assertSame($expectedPrice['price'], $actualPrice->getGross());
        static::assertSame($expectedPrice['currencyId'], $actualPrice->getCurrencyId());
    }

    public function assertInsertNew(): void
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

        $criteria = new Criteria([$shippingMethod['id']]);
        $criteria->addAssociation('prices');

        /** @var ShippingMethodEntity $shippingMethodEntity */
        $shippingMethodEntity = $repository->search($criteria, Context::createDefaultContext())->first();

        /** @var ShippingMethodPriceCollection|null $prices */
        $prices = $shippingMethodEntity->getPrices();
        static::assertNotNull($prices);
        static::assertCount(1, $prices);

        $expectedPrice = $shippingMethod['prices'][0]['currencyPrice'][0];
        $actualPriceEntity = $prices->first();

        static::assertSame($expectedPrice['gross'], $actualPriceEntity->getPrice());
        static::assertSame($expectedPrice['currencyId'], $actualPriceEntity->getCurrencyId());
    }

    public function assertUpdateDeprecated(): void
    {
        $priceId = Uuid::randomHex();
        $shippingMethod = $this->getShippingMethod($priceId);

        $newPrice = [
            'id' => $priceId,
            'currencyId' => Defaults::CURRENCY,
            'price' => 6.15,
        ];
        $update = [
            'id' => $shippingMethod['id'],
            'prices' => [
                $newPrice,
            ],
        ];

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('shipping_method.repository');
        $repository->update([$update], Context::createDefaultContext());

        $criteria = new Criteria([$shippingMethod['id']]);
        $criteria->addAssociation('prices');

        /** @var ShippingMethodEntity $shippingMethodEntity */
        $shippingMethodEntity = $repository->search($criteria, Context::createDefaultContext())->first();

        /** @var ShippingMethodPriceCollection|null $prices */
        $prices = $shippingMethodEntity->getPrices();
        static::assertNotNull($prices);
        static::assertCount(1, $prices);

        $actualPriceEntity = $prices->first();

        static::assertSame($newPrice['price'], $actualPriceEntity->getPrice());
        static::assertSame($newPrice['currencyId'], $actualPriceEntity->getCurrencyId());
    }

    public function assertUpdateNew(): void
    {
        $priceId = Uuid::randomHex();
        $shippingMethod = $this->getShippingMethod($priceId);

        $newPrice = [
            'currencyId' => Defaults::CURRENCY,
            'net' => 5.13,
            'gross' => 6.15,
            'linked' => false,
        ];
        $update = [
            'id' => $shippingMethod['id'],
            'prices' => [
                [
                    'id' => $priceId,
                    'currencyPrice' => [
                        $newPrice,
                    ],
                ],
            ],
        ];

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('shipping_method.repository');
        $repository->update([$update], Context::createDefaultContext());

        $criteria = new Criteria([$shippingMethod['id']]);
        $criteria->addAssociation('prices');

        /** @var ShippingMethodEntity $shippingMethodEntity */
        $shippingMethodEntity = $repository->search($criteria, Context::createDefaultContext())->first();

        /** @var ShippingMethodPriceCollection|null $prices */
        $prices = $shippingMethodEntity->getPrices();
        static::assertNotNull($prices);
        static::assertCount(1, $prices);

        $actualPriceEntity = $prices->first();

        static::assertSame($newPrice['gross'], $actualPriceEntity->getPrice());
        static::assertSame($newPrice['currencyId'], $actualPriceEntity->getCurrencyId());
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
}
