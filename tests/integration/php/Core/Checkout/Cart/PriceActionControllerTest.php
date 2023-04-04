<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxEntity;
use Shopware\Core\System\Test\EntityFixturesBase;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class PriceActionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use EntityFixturesBase;

    /**
     * @var array<string, mixed>
     */
    public $taxFixtures;

    public function testPriceMissingExecption(): void
    {
        $this->getBrowser()->request('POST', '/api/price/actions/calculate');

        /** @var string $response */
        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
    }

    public function testTaxIdMissingException(): void
    {
        $this->getBrowser()->request('POST', '/api/price/actions/calculate', ['price' => 10]);

        /** @var string $response */
        $response = $this->getBrowser()->getResponse()->getContent();

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
    }

    public function testTaxNotFoundException(): void
    {
        $this->getBrowser()->request('POST', '/api/price/actions/calculate', [
            'price' => 10,
            'taxId' => Uuid::randomHex(),
        ]);

        /** @var string $response */
        $response = $this->getBrowser()->getResponse()->getContent();

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
    }

    /**
     * @dataProvider dataProviderTestNetToGross
     */
    public function testNetToGross(float $taxRate, string $getTaxIdFuncName, float $expectedPrice, float $expectedTax): void
    {
        $price = $this->sendRequest([
            'price' => 10,
            'taxId' => $this->{$getTaxIdFuncName}()->getId(),
            'calculated' => false,
        ]);

        static::assertEquals(
            new CalculatedPrice(
                $expectedPrice,
                $expectedPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $taxRate, $expectedPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($taxRate, 100),
                ])
            ),
            $price
        );
    }

    /**
     * @dataProvider dataProviderTestNetToNet
     */
    public function testNetToNet(float $taxRate, string $getTaxIdFuncName, float $expectedPrice, float $expectedTax): void
    {
        $price = $this->sendRequest([
            'price' => 10.002,
            'output' => 'net',
            'taxId' => $this->{$getTaxIdFuncName}()->getId(),
            'calculated' => true,
        ]);

        static::assertEquals(
            new CalculatedPrice(
                $expectedPrice,
                $expectedPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $taxRate, $expectedPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($taxRate, 100),
                ])
            ),
            $price
        );
    }

    /**
     * @dataProvider dataProviderTestGrossToGross
     */
    public function testGrossToGross(float $taxRate, string $getTaxIdFuncName, float $expectedPrice, float $expectedTax): void
    {
        $price = $this->sendRequest([
            'price' => 11.9,
            'taxId' => $this->{$getTaxIdFuncName}()->getId(),
            'calculated' => true,
        ]);

        static::assertEquals(
            new CalculatedPrice(
                $expectedPrice,
                $expectedPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $taxRate, $expectedPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($taxRate, 100),
                ])
            ),
            $price
        );
    }

    /**
     * @dataProvider dataProviderTestNetToGrossWithQuantity
     */
    public function testNetToGrossWithQuantity(float $taxRate, string $getTaxIdFuncName, float $expectedUnitPrice, float $expectedTotalPrice, float $expectedTax): void
    {
        $price = $this->sendRequest([
            'price' => 10,
            'quantity' => 2,
            'taxId' => $this->{$getTaxIdFuncName}()->getId(),
            'calculated' => false,
        ]);

        static::assertEquals(
            new CalculatedPrice(
                $expectedUnitPrice,
                $expectedTotalPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $taxRate, $expectedTotalPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($taxRate, 100),
                ]),
                2
            ),
            $price
        );
    }

    /**
     * @dataProvider dataProviderTestGrossToGrossWithQuantity
     */
    public function testGrossToGrossWithQuantity(float $taxRate, string $getTaxIdFuncName, float $expectedUnitPrice, float $expectedTotalPrice, float $expectedTax): void
    {
        $price = $this->sendRequest([
            'price' => 10,
            'quantity' => 2,
            'taxId' => $this->{$getTaxIdFuncName}()->getId(),
            'calculated' => true,
        ]);

        static::assertEquals(
            new CalculatedPrice(
                $expectedUnitPrice,
                $expectedTotalPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $taxRate, $expectedTotalPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($taxRate, 100),
                ]),
                2
            ),
            $price
        );
    }

    /**
     * @dataProvider dataProviderTestGrossToNet
     */
    public function testGrossToNet(float $taxRate, string $getTaxIdFuncName, float $expectedUnitPrice, float $expectedTotalPrice, float $expectedTax): void
    {
        $price = $this->sendRequest([
            'price' => 11.9,
            'output' => 'net',
            'taxId' => $this->{$getTaxIdFuncName}()->getId(),
            'calculated' => true,
        ]);

        static::assertEquals(
            new CalculatedPrice(
                $expectedUnitPrice,
                $expectedTotalPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $taxRate, $expectedTotalPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($taxRate, 100),
                ])
            ),
            $price
        );
    }

    public function testCalculatePricesMissingException(): void
    {
        $this->getBrowser()->request('POST', 'api/_action/calculate-prices');

        /** @var string $response */
        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
    }

    public function testCalculatePricesTaxIdMissingException(): void
    {
        $this->getBrowser()->request('POST', 'api/_action/calculate-prices', ['prices' => [['price' => 10]]]);

        /** @var string $response */
        $response = $this->getBrowser()->getResponse()->getContent();

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
    }

    public function testCalculatePricesTaxNotFoundException(): void
    {
        $productId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $this->getBrowser()->request('POST', 'api/_action/calculate-prices', [
            'prices' => [
                $productId => [['price' => 10]],
            ],
            'currencyId' => Uuid::randomHex(),
            'taxId' => Uuid::randomHex(),
        ]);

        /** @var string $response */
        $response = $this->getBrowser()->getResponse()->getContent();

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
    }

    /**
     * @dataProvider dataProviderTestCalculatePricesNetToGross
     */
    public function testCalculatePricesNetToGross(float $taxRate, string $getTaxIdFuncName, float $expectedUnitPrice, float $expectedTotalPrice, float $expectedTax): void
    {
        $productId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $price = $this->sendRequestCalculatePrices([
            'prices' => [
                $productId => [[
                    'price' => 10,
                    'currencyId' => $currencyId,
                    'calculated' => false,
                ]],
            ],
            'taxId' => $this->{$getTaxIdFuncName}()->getId(),
        ], $productId, $currencyId);

        static::assertEquals(
            new CalculatedPrice(
                $expectedUnitPrice,
                $expectedTotalPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $taxRate, $expectedTotalPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($taxRate, 100),
                ])
            ),
            $price
        );
    }

    /**
     * @dataProvider dataProviderTestCalculatePricesNetToNet
     */
    public function testCalculatePricesNetToNet(float $taxRate, string $getTaxIdFuncName, float $expectedUnitPrice, float $expectedTotalPrice, float $expectedTax): void
    {
        $productId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $price = $this->sendRequestCalculatePrices([
            'prices' => [
                $productId => [[
                    'price' => 10.002,
                    'output' => 'net',
                    'currencyId' => $currencyId,
                    'calculated' => false,
                ]],
            ],
            'taxId' => $this->{$getTaxIdFuncName}()->getId(),
        ], $productId, $currencyId);

        static::assertEquals(
            new CalculatedPrice(
                $expectedUnitPrice,
                $expectedTotalPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $taxRate, $expectedTotalPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($taxRate, 100),
                ])
            ),
            $price
        );
    }

    /**
     * @dataProvider dataProviderTestCalculatePricesGrossToGross
     */
    public function testCalculatePricesGrossToGross(float $taxRate, string $getTaxIdFuncName, float $expectedUnitPrice, float $expectedTotalPrice, float $expectedTax): void
    {
        $productId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $price = $this->sendRequestCalculatePrices([
            'prices' => [
                $productId => [[
                    'price' => 11.9,
                    'currencyId' => $currencyId,
                    'calculated' => true,
                ]],
            ],
            'taxId' => $this->{$getTaxIdFuncName}()->getId(),
        ], $productId, $currencyId);

        static::assertEquals(
            new CalculatedPrice(
                $expectedUnitPrice,
                $expectedTotalPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $taxRate, $expectedTotalPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($taxRate, 100),
                ])
            ),
            $price
        );
    }

    /**
     * @dataProvider dataProviderTestCalculatePricesNetToGrossWithQuantity
     */
    public function testCalculatePricesNetToGrossWithQuantity(float $taxRate, string $getTaxIdFuncName, float $expectedUnitPrice, float $expectedTotalPrice, float $expectedTax): void
    {
        $productId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $price = $this->sendRequestCalculatePrices([
            'prices' => [
                $productId => [[
                    'price' => 10,
                    'quantity' => 2,
                    'currencyId' => $currencyId,
                    'calculated' => false,
                ]],
            ],
            'taxId' => $this->{$getTaxIdFuncName}()->getId(),
        ], $productId, $currencyId);

        static::assertEquals(
            new CalculatedPrice(
                $expectedUnitPrice,
                $expectedTotalPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $taxRate, $expectedTotalPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($taxRate, 100),
                ]),
                2
            ),
            $price
        );
    }

    /**
     * @dataProvider dataProviderTestCalculatePricesGrossToGrossWithQuantity
     */
    public function testCalculatePricesGrossToGrossWithQuantity(float $taxRate, string $getTaxIdFuncName, float $expectedUnitPrice, float $expectedTotalPrice, float $expectedTax): void
    {
        $productId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $price = $this->sendRequestCalculatePrices([
            'prices' => [
                $productId => [[
                    'price' => 10,
                    'quantity' => 2,
                    'currencyId' => $currencyId,
                    'calculated' => true,
                ]],
            ],
            'taxId' => $this->{$getTaxIdFuncName}()->getId(),
        ], $productId, $currencyId);

        static::assertEquals(
            new CalculatedPrice(
                $expectedUnitPrice,
                $expectedTotalPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $taxRate, $expectedTotalPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($taxRate, 100),
                ]),
                2
            ),
            $price
        );
    }

    /**
     * @dataProvider dataProviderTestCalculatePricesGrossToNet
     */
    public function testCalculatePricesGrossToNet(float $taxRate, string $getTaxIdFuncName, float $expectedUnitPrice, float $expectedTotalPrice, float $expectedTax): void
    {
        $productId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $price = $this->sendRequestCalculatePrices([
            'prices' => [
                $productId => [[
                    'price' => 11.9,
                    'output' => 'net',
                    'currencyId' => $currencyId,
                    'calculated' => true,
                ]],
            ],
            'taxId' => $this->{$getTaxIdFuncName}()->getId(),
        ], $productId, $currencyId);

        static::assertEquals(
            new CalculatedPrice(
                $expectedUnitPrice,
                $expectedTotalPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $taxRate, $expectedTotalPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($taxRate, 100),
                ])
            ),
            $price
        );
    }

    public static function dataProviderTestNetToNet(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'taxRate' => 19,
            'getTaxIdFuncName' => 'getTaxNineteenPercent',
            'expectedPrice' => 10.002,
            'expectedTax' => 1.90038,
        ];

        yield 'Case with tax rate 17.1' => [
            'taxRate' => 17.1,
            'getTaxIdFuncName' => 'getTaxSeventeenPointOnePercent',
            'expectedPrice' => 10.002,
            'expectedTax' => 1.710342,
        ];

        yield 'Case with tax rate 9.74' => [
            'taxRate' => 9.74,
            'getTaxIdFuncName' => 'getTaxNinePointSevenFourPercent',
            'expectedPrice' => 10.002,
            'expectedTax' => 0.9741948,
        ];

        yield 'Case with tax rate 13.486' => [
            'taxRate' => 13.486,
            'getTaxIdFuncName' => 'getTaxThirteenPointFourEightSixPercent',
            'expectedPrice' => 10.002,
            'expectedTax' => 1.34886972,
        ];
    }

    public static function dataProviderTestNetToGross(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'taxRate' => 19,
            'getTaxIdFuncName' => 'getTaxNineteenPercent',
            'expectedPrice' => 11.9,
            'expectedTax' => 1.9,
        ];

        yield 'Case with tax rate 17.1' => [
            'taxRate' => 17.1,
            'getTaxIdFuncName' => 'getTaxSeventeenPointOnePercent',
            'expectedPrice' => 11.71,
            'expectedTax' => 1.71,
        ];

        yield 'Case with tax rate 9.74' => [
            'taxRate' => 9.74,
            'getTaxIdFuncName' => 'getTaxNinePointSevenFourPercent',
            'expectedPrice' => 10.974,
            'expectedTax' => 0.974,
        ];

        yield 'Case with tax rate 13.486' => [
            'taxRate' => 13.486,
            'getTaxIdFuncName' => 'getTaxThirteenPointFourEightSixPercent',
            'expectedPrice' => 11.3486,
            'expectedTax' => 1.3486,
        ];
    }

    public static function dataProviderTestGrossToGross(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'taxRate' => 19,
            'getTaxIdFuncName' => 'getTaxNineteenPercent',
            'expectedPrice' => 11.9,
            'expectedTax' => 1.9,
        ];

        yield 'Case with tax rate 17.1' => [
            'taxRate' => 17.1,
            'getTaxIdFuncName' => 'getTaxSeventeenPointOnePercent',
            'expectedPrice' => 11.9,
            'expectedTax' => 1.7377455166524,
        ];

        yield 'Case with tax rate 9.74' => [
            'taxRate' => 9.74,
            'getTaxIdFuncName' => 'getTaxNinePointSevenFourPercent',
            'expectedPrice' => 11.9,
            'expectedTax' => 1.0561873519227,
        ];

        yield 'Case with tax rate 13.486' => [
            'taxRate' => 13.486,
            'getTaxIdFuncName' => 'getTaxThirteenPointFourEightSixPercent',
            'expectedPrice' => 11.9,
            'expectedTax' => 1.4141250903195,
        ];
    }

    public static function dataProviderTestNetToGrossWithQuantity(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'taxRate' => 19,
            'getTaxIdFuncName' => 'getTaxNineteenPercent',
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 23.8,
            'expectedTax' => 3.8,
        ];

        yield 'Case with tax rate 17.1' => [
            'taxRate' => 17.1,
            'getTaxIdFuncName' => 'getTaxSeventeenPointOnePercent',
            'expectedUnitPrice' => 11.71,
            'expectedTotalPrice' => 23.42,
            'expectedTax' => 3.42,
        ];

        yield 'Case with tax rate 9.74' => [
            'taxRate' => 9.74,
            'getTaxIdFuncName' => 'getTaxNinePointSevenFourPercent',
            'expectedUnitPrice' => 10.974,
            'expectedTotalPrice' => 21.948,
            'expectedTax' => 1.948,
        ];

        yield 'Case with tax rate 13.486' => [
            'taxRate' => 13.486,
            'getTaxIdFuncName' => 'getTaxThirteenPointFourEightSixPercent',
            'expectedUnitPrice' => 11.3486,
            'expectedTotalPrice' => 22.6972,
            'expectedTax' => 2.6972,
        ];
    }

    public static function dataProviderTestGrossToGrossWithQuantity(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'taxRate' => 19,
            'getTaxIdFuncName' => 'getTaxNineteenPercent',
            'expectedUnitPrice' => 10,
            'expectedTotalPrice' => 20,
            'expectedTax' => 3.1932773109244,
        ];

        yield 'Case with tax rate 17.1' => [
            'taxRate' => 17.1,
            'getTaxIdFuncName' => 'getTaxSeventeenPointOnePercent',
            'expectedUnitPrice' => 10,
            'expectedTotalPrice' => 20,
            'expectedTax' => 2.9205807002562,
        ];

        yield 'Case with tax rate 9.74' => [
            'taxRate' => 9.74,
            'getTaxIdFuncName' => 'getTaxNinePointSevenFourPercent',
            'expectedUnitPrice' => 10,
            'expectedTotalPrice' => 20,
            'expectedTax' => 1.7751047931474,
        ];

        yield 'Case with tax rate 13.486' => [
            'taxRate' => 13.486,
            'getTaxIdFuncName' => 'getTaxThirteenPointFourEightSixPercent',
            'expectedUnitPrice' => 10,
            'expectedTotalPrice' => 20,
            'expectedTax' => 2.3766808240664,
        ];
    }

    public static function dataProviderTestGrossToNet(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'taxRate' => 19,
            'getTaxIdFuncName' => 'getTaxNineteenPercent',
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 2.261,
        ];

        yield 'Case with tax rate 17.1' => [
            'taxRate' => 17.1,
            'getTaxIdFuncName' => 'getTaxSeventeenPointOnePercent',
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 2.0349,
        ];

        yield 'Case with tax rate 9.74' => [
            'taxRate' => 9.74,
            'getTaxIdFuncName' => 'getTaxNinePointSevenFourPercent',
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 1.15906,
        ];

        yield 'Case with tax rate 13.486' => [
            'taxRate' => 13.486,
            'getTaxIdFuncName' => 'getTaxThirteenPointFourEightSixPercent',
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 1.604834,
        ];
    }

    public static function dataProviderTestCalculatePricesNetToGross(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'taxRate' => 19,
            'getTaxIdFuncName' => 'getTaxNineteenPercent',
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 1.9,
        ];

        yield 'Case with tax rate 17.1' => [
            'taxRate' => 17.1,
            'getTaxIdFuncName' => 'getTaxSeventeenPointOnePercent',
            'expectedUnitPrice' => 11.71,
            'expectedTotalPrice' => 11.71,
            'expectedTax' => 1.71,
        ];

        yield 'Case with tax rate 9.74' => [
            'taxRate' => 9.74,
            'getTaxIdFuncName' => 'getTaxNinePointSevenFourPercent',
            'expectedUnitPrice' => 10.974,
            'expectedTotalPrice' => 10.974,
            'expectedTax' => 0.974,
        ];

        yield 'Case with tax rate 13.486' => [
            'taxRate' => 13.486,
            'getTaxIdFuncName' => 'getTaxThirteenPointFourEightSixPercent',
            'expectedUnitPrice' => 11.3486,
            'expectedTotalPrice' => 11.3486,
            'expectedTax' => 1.3486,
        ];
    }

    public static function dataProviderTestCalculatePricesNetToNet(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'taxRate' => 19,
            'getTaxIdFuncName' => 'getTaxNineteenPercent',
            'expectedUnitPrice' => 10.002,
            'expectedTotalPrice' => 10.002,
            'expectedTax' => 1.9003800000000002,
        ];

        yield 'Case with tax rate 17.1' => [
            'taxRate' => 17.1,
            'getTaxIdFuncName' => 'getTaxSeventeenPointOnePercent',
            'expectedUnitPrice' => 10.002,
            'expectedTotalPrice' => 10.002,
            'expectedTax' => 1.710342,
        ];

        yield 'Case with tax rate 9.74' => [
            'taxRate' => 9.74,
            'getTaxIdFuncName' => 'getTaxNinePointSevenFourPercent',
            'expectedUnitPrice' => 10.002,
            'expectedTotalPrice' => 10.002,
            'expectedTax' => 0.9741948,
        ];

        yield 'Case with tax rate 13.486' => [
            'taxRate' => 13.486,
            'getTaxIdFuncName' => 'getTaxThirteenPointFourEightSixPercent',
            'expectedUnitPrice' => 10.002,
            'expectedTotalPrice' => 10.002,
            'expectedTax' => 1.34886972,
        ];
    }

    public static function dataProviderTestCalculatePricesGrossToGross(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'taxRate' => 19,
            'getTaxIdFuncName' => 'getTaxNineteenPercent',
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 1.9,
        ];

        yield 'Case with tax rate 17.1' => [
            'taxRate' => 17.1,
            'getTaxIdFuncName' => 'getTaxSeventeenPointOnePercent',
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 1.7377455166524,
        ];

        yield 'Case with tax rate 9.74' => [
            'taxRate' => 9.74,
            'getTaxIdFuncName' => 'getTaxNinePointSevenFourPercent',
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 1.0561873519227,
        ];

        yield 'Case with tax rate 13.486' => [
            'taxRate' => 13.486,
            'getTaxIdFuncName' => 'getTaxThirteenPointFourEightSixPercent',
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 1.4141250903195,
        ];
    }

    public static function dataProviderTestCalculatePricesNetToGrossWithQuantity(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'taxRate' => 19,
            'getTaxIdFuncName' => 'getTaxNineteenPercent',
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 23.8,
            'expectedTax' => 3.8,
        ];

        yield 'Case with tax rate 17.1' => [
            'taxRate' => 17.1,
            'getTaxIdFuncName' => 'getTaxSeventeenPointOnePercent',
            'expectedUnitPrice' => 11.71,
            'expectedTotalPrice' => 23.42,
            'expectedTax' => 3.42,
        ];

        yield 'Case with tax rate 9.74' => [
            'taxRate' => 9.74,
            'getTaxIdFuncName' => 'getTaxNinePointSevenFourPercent',
            'expectedUnitPrice' => 10.974,
            'expectedTotalPrice' => 21.948,
            'expectedTax' => 1.948,
        ];

        yield 'Case with tax rate 13.486' => [
            'taxRate' => 13.486,
            'getTaxIdFuncName' => 'getTaxThirteenPointFourEightSixPercent',
            'expectedUnitPrice' => 11.3486,
            'expectedTotalPrice' => 22.6972,
            'expectedTax' => 2.6972,
        ];
    }

    public static function dataProviderTestCalculatePricesGrossToGrossWithQuantity(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'taxRate' => 19,
            'getTaxIdFuncName' => 'getTaxNineteenPercent',
            'expectedUnitPrice' => 10,
            'expectedTotalPrice' => 20,
            'expectedTax' => 3.19327731092437,
        ];

        yield 'Case with tax rate 17.1' => [
            'taxRate' => 17.1,
            'getTaxIdFuncName' => 'getTaxSeventeenPointOnePercent',
            'expectedUnitPrice' => 10,
            'expectedTotalPrice' => 20,
            'expectedTax' => 2.9205807002562,
        ];

        yield 'Case with tax rate 9.74' => [
            'taxRate' => 9.74,
            'getTaxIdFuncName' => 'getTaxNinePointSevenFourPercent',
            'expectedUnitPrice' => 10,
            'expectedTotalPrice' => 20,
            'expectedTax' => 1.7751047931474,
        ];

        yield 'Case with tax rate 13.486' => [
            'taxRate' => 13.486,
            'getTaxIdFuncName' => 'getTaxThirteenPointFourEightSixPercent',
            'expectedUnitPrice' => 10,
            'expectedTotalPrice' => 20,
            'expectedTax' => 2.3766808240664,
        ];
    }

    public static function dataProviderTestCalculatePricesGrossToNet(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'taxRate' => 19,
            'getTaxIdFuncName' => 'getTaxNineteenPercent',
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 2.261,
        ];

        yield 'Case with tax rate 17.1' => [
            'taxRate' => 17.1,
            'getTaxIdFuncName' => 'getTaxSeventeenPointOnePercent',
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 2.0349,
        ];

        yield 'Case with tax rate 9.74' => [
            'taxRate' => 9.74,
            'getTaxIdFuncName' => 'getTaxNinePointSevenFourPercent',
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 1.15906,
        ];

        yield 'Case with tax rate 13.486' => [
            'taxRate' => 13.486,
            'getTaxIdFuncName' => 'getTaxThirteenPointFourEightSixPercent',
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 1.604834,
        ];
    }

    /**
     * @before
     */
    public function initializeTaxFixtures(): void
    {
        $this->taxFixtures = [
            'NineteenPercentTax' => [
                'id' => Uuid::randomHex(),
                'name' => 'NineteenPercentTax',
                'taxRate' => 19,
            ],
            'NineteenPercentTaxWithAreaRule' => [
                'id' => Uuid::randomHex(),
                'name' => 'foo tax',
                'taxRate' => 20,
                'areaRules' => [
                    [
                        'id' => Uuid::randomHex(),
                        'taxRate' => 99,
                        'active' => true,
                        'name' => 'required',
                        'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                    ],
                ],
            ],
            'SeventeenPointOnePercentTax' => [
                'id' => Uuid::randomHex(),
                'name' => 'SeventeenPointOnePercentTax',
                'taxRate' => 17.1,
                'areaRules' => [
                    [
                        'id' => Uuid::randomHex(),
                        'taxRate' => 17.1,
                        'active' => true,
                        'name' => 'required',
                        'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                    ],
                ],
            ],
            'NinePointSevenFourPercentTax' => [
                'id' => Uuid::randomHex(),
                'name' => 'NinePointSevenFourPercentTax',
                'taxRate' => 9.74,
                'areaRules' => [
                    [
                        'id' => Uuid::randomHex(),
                        'taxRate' => 9.74,
                        'active' => true,
                        'name' => 'required',
                        'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                    ],
                ],
            ],
            'ThirteenPointFourEightSixPercentTax' => [
                'id' => Uuid::randomHex(),
                'name' => 'ThirteenPointFourEightSixPercentTax',
                'taxRate' => 13.486,
                'areaRules' => [
                    [
                        'id' => Uuid::randomHex(),
                        'taxRate' => 13.486,
                        'active' => true,
                        'name' => 'required',
                        'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                    ],
                ],
            ],
        ];
    }

    public function getTaxNineteenPercent(): TaxEntity
    {
        return $this->getTaxFixture('NineteenPercentTax');
    }

    public function getTaxSeventeenPointOnePercent(): TaxEntity
    {
        return $this->getTaxFixture('SeventeenPointOnePercentTax');
    }

    public function getTaxNinePointSevenFourPercent(): TaxEntity
    {
        return $this->getTaxFixture('NinePointSevenFourPercentTax');
    }

    public function getTaxThirteenPointFourEightSixPercent(): TaxEntity
    {
        return $this->getTaxFixture('ThirteenPointFourEightSixPercentTax');
    }

    public function getTaxNineteenPercentWithAreaRule(): TaxEntity
    {
        return $this->getTaxFixture('NineteenPercentTaxWithAreaRule');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function sendRequest(array $data): CalculatedPrice
    {
        $url = '/api/_action/calculate-price';
        $this->getBrowser()->request('POST', $url, $data);

        /** @var string $response */
        $response = $this->getBrowser()->getResponse()->getContent();

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('data', $response);

        $data = $response['data'];

        return new CalculatedPrice(
            $data['unitPrice'],
            $data['totalPrice'],
            new CalculatedTaxCollection(
                array_map(fn ($row) => new CalculatedTax($row['tax'], $row['taxRate'], $row['price']), $data['calculatedTaxes'])
            ),
            new TaxRuleCollection(array_map(fn ($row) => new TaxRule($row['taxRate'], $row['percentage']), $data['taxRules'])),
            $data['quantity']
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function sendRequestCalculatePrices(array $data, string $productId, string $currencyId): CalculatedPrice
    {
        $url = '/api/_action/calculate-prices';
        $this->getBrowser()->request('POST', $url, $data);

        /** @var string $response */
        $response = $this->getBrowser()->getResponse()->getContent();

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('data', $response);

        $data = $response['data'];
        static::assertArrayHasKey($productId, $data);

        $data = $data[$productId];
        static::assertArrayHasKey($currencyId, $data);

        $data = $data[$currencyId];

        return new CalculatedPrice(
            $data['unitPrice'],
            $data['totalPrice'],
            new CalculatedTaxCollection(
                array_map(fn ($row) => new CalculatedTax($row['tax'], $row['taxRate'], $row['price']), $data['calculatedTaxes'])
            ),
            new TaxRuleCollection(array_map(fn ($row) => new TaxRule($row['taxRate'], $row['percentage']), $data['taxRules'])),
            $data['quantity']
        );
    }

    private function getTaxFixture(string $fixtureName): TaxEntity
    {
        /** @var TaxEntity $taxEntity */
        $taxEntity = $this->createFixture(
            $fixtureName,
            $this->taxFixtures,
            self::getFixtureRepository('tax')
        );

        return $taxEntity;
    }
}
