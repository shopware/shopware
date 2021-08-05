<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductSubscriber;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SalesChannelProductSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @dataProvider maxPurchaseProvider
     */
    public function testMaxPurchaseCalculation(int $expected, bool $closeout, int $stock, int $steps, ?int $max, int $config): void
    {
        $method = new \ReflectionClass(SalesChannelProductSubscriber::class);
        $method = $method->getMethod('calculateMaxPurchase');
        $method->setAccessible(true);

        $this->getContainer()->get(SystemConfigService::class)
            ->set('core.cart.maxQuantity', $config);

        $product = new SalesChannelProductEntity();
        $product->setIsCloseout($closeout);

        if ($max) {
            $product->setMaxPurchase($max);
        }

        $product->setAvailableStock($stock);
        $product->setPurchaseSteps($steps);

        $subscriber = $this->getContainer()->get(SalesChannelProductSubscriber::class);

        $calculated = $method->invoke($subscriber, $product, Defaults::SALES_CHANNEL);

        static::assertSame($expected, $calculated);
    }

    public function maxPurchaseProvider()
    {
        // expected, closeout, stock, steps, max, config
        yield 'should use configured max purchase' => [10, false, 25, 1, 10, 100];
        yield 'less stock, but not closeout' => [10, false, 1, 1, 10, 100];
        yield 'not configured, fallback to config' => [20, false, 5, 1, null, 20];
        yield 'closeout with less stock' => [2, true, 2, 1, 10, 100];
        yield 'use configured max purchase for closeout with stock' => [10, true, 30, 1, 10, 50];
        yield 'not configured, use stock because closeout' => [2, true, 2, 1, null, 50];
        yield 'next step would be higher than available' => [7, true, 9, 6, 20, 20];
        yield 'second step would be higher than available' => [13, true, 13, 6, 20, 20];
        yield 'max config is not in steps' => [13, true, 100, 12, 22, 22];
        yield 'max config is last step' => [15, false, 100, 2, 15, 15];
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
                    'itemRounding' => json_decode(json_encode(new CashRoundingConfig(3, 0.01, true)), true),
                    'totalRounding' => json_decode(json_encode(new CashRoundingConfig(3, 0.01, true)), true),
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

            $price = [
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
            ];
            if ($case->currencyId !== Defaults::CURRENCY) {
                $price[] = [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 1,
                    'net' => 1,
                    'linked' => false,
                ];
            }

            $data = array_merge($defaults, [
                'id' => $id,
                'productNumber' => $id,
                'price' => $price,
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
