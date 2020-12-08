<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SalesChannelProductSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testMaxQuantityCalculation(): void
    {
        $ids = new TestDataCollection(Context::createDefaultContext());

        $defaults = [
            'name' => 'test',
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'visibilities' => [
                ['salesChannelId' => Defaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $cases = [
            new MaxPurchaseTestCase(10, false, 25, 10, 100, 'should use configured max purchase'),
            new MaxPurchaseTestCase(10, false, 1, 10, 100, 'less stock, but not closeout'),
            new MaxPurchaseTestCase(20, false, 5, null, 20, 'not configured, fallback to config'),

            new MaxPurchaseTestCase(2, true, 2, 10, 100, 'closeout with less stock'),
            new MaxPurchaseTestCase(10, true, 30, 10, 50, 'use configured max purchase for closeout with stock'),
            new MaxPurchaseTestCase(2, true, 2, null, 50, 'not configured, use stock because closeout'),
        ];

        foreach ($cases as $i => $case) {
            $id = $ids->create('product-' . $i);

            $data = array_merge($defaults, [
                'id' => $id,
                'productNumber' => $id,

                'maxPurchase' => $case->maxPurchase,
                'isCloseout' => $case->isCloseout,
                'stock' => $case->stock,
            ]);

            $this->getContainer()->get('product.repository')
                ->create([$data], $ids->getContext());

            $context = $this->getContainer()->get(SalesChannelContextFactory::class)
                ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

            $this->getContainer()->get(SystemConfigService::class)
                ->set('core.cart.maxQuantity', $case->config);

            /** @var SalesChannelProductEntity|null $product */
            $product = $this->getContainer()->get('sales_channel.product.repository')
                ->search(new Criteria([$id]), $context)
                ->get($id);

            static::assertInstanceOf(SalesChannelProductEntity::class, $product);

            static::assertSame($case->expected, $product->getCalculatedMaxPurchase(), $case->description);
        }
    }

    public function testListPrices(): void
    {
        $ids = new TestDataCollection(Context::createDefaultContext());

        $taxId = $this->getContainer()->get(Connection::class)
            ->fetchColumn('SELECT LOWER(HEX(id)) FROM tax LIMIT 1');

        $this->getContainer()->get('currency.repository')
            ->create([
                [
                    'id' => $ids->create('currency'),
                    'name' => 'test',
                    'shortName' => 'test',
                    'factor' => 1.5,
                    'symbol' => 'XXX',
                    'isoCode' => 'XX',
                    'decimalPrecision' => 3,
                ],
            ], $ids->context);

        $defaults = [
            'id' => 1,
            'name' => 'test',
            'stock' => 10,
            'taxId' => $taxId,
            'visibilities' => [
                ['salesChannelId' => Defaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $cases = [
            new ListPriceTestCase(100, 90, 200, 90, 50, CartPrice::TAX_STATE_GROSS, -100, 100, 200),
            new ListPriceTestCase(100, 90, 200, 135, 33.33, CartPrice::TAX_STATE_NET, -45, 90, 135),
            new ListPriceTestCase(100, 90, 200, 135, 33.33, CartPrice::TAX_STATE_FREE, -45, 90, 135),

            new ListPriceTestCase(100, 90, 200, 90, 50, CartPrice::TAX_STATE_GROSS, -100, 100, 200, $ids->get('currency'), $ids->get('currency')),
            new ListPriceTestCase(100, 90, 200, 135, 33.33, CartPrice::TAX_STATE_NET, -45, 90, 135, $ids->get('currency'), $ids->get('currency')),
            new ListPriceTestCase(100, 90, 200, 135, 33.33, CartPrice::TAX_STATE_FREE, -45, 90, 135, $ids->get('currency'), $ids->get('currency')),

            new ListPriceTestCase(100, 90, 200, 90, 50, CartPrice::TAX_STATE_GROSS, -150, 150, 300, Defaults::CURRENCY, $ids->get('currency')),
            new ListPriceTestCase(100, 90, 200, 135, 33.33, CartPrice::TAX_STATE_NET, -67.5, 135, 202.5, Defaults::CURRENCY, $ids->get('currency')),
            new ListPriceTestCase(100, 90, 200, 135, 33.33, CartPrice::TAX_STATE_FREE, -67.5, 135, 202.5, Defaults::CURRENCY, $ids->get('currency')),
        ];

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        foreach ($cases as $i => $case) {
            // prepare currency factor calculation
            $factor = 1;
            if ($case->usedCurrency !== Defaults::CURRENCY) {
                $factor = 1.5;
            }

            $context->getContext()->assign(['currencyFactor' => $factor]);
            $context->getCurrency()->setId($case->usedCurrency);

            // test different tax states
            $context->setTaxState($case->taxState);

            // create a new product for this case
            $id = $ids->create('product-' . $i);

            $data = array_merge($defaults, [
                'id' => $id,
                'productNumber' => $id,
                'price' => [
                    [
                        'currencyId' => $case->currencyId,
                        'gross' => $case->gross,
                        'net' => $case->net,
                        'linked' => false,
                        'listPrice' => [
                            'gross' => $case->wasGross,
                            'net' => $case->wasNet,
                            'linked' => false,
                        ],
                    ],
                ],
            ]);

            $this->getContainer()->get('product.repository')
                ->create([$data], $ids->context);

            $product = $this->getContainer()->get('sales_channel.product.repository')
                ->search(new Criteria([$id]), $context)
                ->get($id);

            static::assertInstanceOf(SalesChannelProductEntity::class, $product);

            $price = $product->getCalculatedPrice();

            static::assertInstanceOf(ListPrice::class, $price->getListPrice());

            static::assertEquals($case->expectedPrice, $price->getUnitPrice());
            static::assertEquals($case->expectedWas, $price->getListPrice()->getPrice());

            static::assertEquals($case->percentage, $price->getListPrice()->getPercentage());
            static::assertEquals($case->discount, $price->getListPrice()->getDiscount());
        }
    }
}

class ListPriceTestCase
{
    /**
     * @var float
     */
    public $gross;

    /**
     * @var float
     */
    public $net;

    /**
     * @var float
     */
    public $wasGross;

    /**
     * @var float
     */
    public $wasNet;

    /**
     * @var string
     */
    public $currencyId;

    /**
     * @var float
     */
    public $percentage;

    /**
     * @var string
     */
    public $taxState;

    /**
     * @var float
     */
    public $discount;

    /**
     * @var string
     */
    public $usedCurrency;

    /**
     * @var float
     */
    public $expectedPrice;

    /**
     * @var float
     */
    public $expectedWas;

    public function __construct(
        float $gross,
        float $net,
        float $wasGross,
        float $wasNet,
        float $percentage,
        string $taxState,
        float $discount,
        float $expectedPrice,
        float $expectedWas,
        string $currencyId = Defaults::CURRENCY,
        string $usedCurrency = Defaults::CURRENCY
    ) {
        $this->gross = $gross;
        $this->net = $net;
        $this->wasGross = $wasGross;
        $this->wasNet = $wasNet;
        $this->currencyId = $currencyId;
        $this->percentage = $percentage;
        $this->taxState = $taxState;
        $this->discount = $discount;
        $this->usedCurrency = $usedCurrency;
        $this->expectedPrice = $expectedPrice;
        $this->expectedWas = $expectedWas;
    }
}

class MaxPurchaseTestCase
{
    /**
     * @var bool
     */
    public $isCloseout;

    /**
     * @var int
     */
    public $stock;

    /**
     * @var int|null
     */
    public $maxPurchase;

    /**
     * @var int
     */
    public $expected;

    /**
     * @var int
     */
    public $config;

    /**
     * @var string
     */
    public $description;

    public function __construct(int $expected, bool $isCloseout, int $stock, ?int $maxPurchase, int $config, string $description)
    {
        $this->isCloseout = $isCloseout;
        $this->stock = $stock;
        $this->maxPurchase = $maxPurchase;
        $this->expected = $expected;
        $this->config = $config;
        $this->description = $description;
    }
}
