<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class PriceActionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

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
     * @param array{id: string, name: string, taxRate: float, areaRules?: list<array<string, mixed>>} $tax
     */
    #[DataProvider('dataProviderTestNetToGross')]
    public function testNetToGross(array $tax, float $expectedPrice, float $expectedTax): void
    {
        $this->getContainer()->get('tax.repository')->create([$tax], Context::createDefaultContext());

        $price = $this->sendRequest([
            'price' => 10,
            'taxId' => $tax['id'],
            'calculated' => false,
        ]);

        static::assertEquals(
            new CalculatedPrice(
                $expectedPrice,
                $expectedPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $tax['taxRate'], $expectedPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($tax['taxRate'], 100),
                ])
            ),
            $price
        );
    }

    /**
     * @param array{id: string, name: string, taxRate: float, areaRules?: list<array<string, mixed>>} $tax
     */
    #[DataProvider('dataProviderTestNetToNet')]
    public function testNetToNet(array $tax, float $expectedPrice, float $expectedTax): void
    {
        $this->getContainer()->get('tax.repository')->create([$tax], Context::createDefaultContext());

        $price = $this->sendRequest([
            'price' => 10.002,
            'output' => 'net',
            'taxId' => $tax['id'],
            'calculated' => true,
        ]);

        static::assertEquals(
            new CalculatedPrice(
                $expectedPrice,
                $expectedPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $tax['taxRate'], $expectedPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($tax['taxRate'], 100),
                ])
            ),
            $price
        );
    }

    /**
     * @param array{id: string, name: string, taxRate: float, areaRules?: list<array<string, mixed>>} $tax
     */
    #[DataProvider('dataProviderTestGrossToGross')]
    public function testGrossToGross(array $tax, float $expectedPrice, float $expectedTax): void
    {
        $this->getContainer()->get('tax.repository')->create([$tax], Context::createDefaultContext());

        $price = $this->sendRequest([
            'price' => 11.9,
            'taxId' => $tax['id'],
            'calculated' => true,
        ]);

        static::assertEquals(
            new CalculatedPrice(
                $expectedPrice,
                $expectedPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $tax['taxRate'], $expectedPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($tax['taxRate'], 100),
                ])
            ),
            $price
        );
    }

    /**
     * @param array{id: string, name: string, taxRate: float, areaRules?: list<array<string, mixed>>} $tax
     */
    #[DataProvider('dataProviderTestNetToGrossWithQuantity')]
    public function testNetToGrossWithQuantity(array $tax, float $expectedUnitPrice, float $expectedTotalPrice, float $expectedTax): void
    {
        $this->getContainer()->get('tax.repository')->create([$tax], Context::createDefaultContext());

        $price = $this->sendRequest([
            'price' => 10,
            'quantity' => 2,
            'taxId' => $tax['id'],
            'calculated' => false,
        ]);

        static::assertEquals(
            new CalculatedPrice(
                $expectedUnitPrice,
                $expectedTotalPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $tax['taxRate'], $expectedTotalPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($tax['taxRate'], 100),
                ]),
                2
            ),
            $price
        );
    }

    /**
     * @param array{id: string, name: string, taxRate: float, areaRules?: list<array<string, mixed>>} $tax
     */
    #[DataProvider('dataProviderTestGrossToGrossWithQuantity')]
    public function testGrossToGrossWithQuantity(array $tax, float $expectedUnitPrice, float $expectedTotalPrice, float $expectedTax): void
    {
        $this->getContainer()->get('tax.repository')->create([$tax], Context::createDefaultContext());

        $price = $this->sendRequest([
            'price' => 10,
            'quantity' => 2,
            'taxId' => $tax['id'],
            'calculated' => true,
        ]);

        static::assertEquals(
            new CalculatedPrice(
                $expectedUnitPrice,
                $expectedTotalPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $tax['taxRate'], $expectedTotalPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($tax['taxRate'], 100),
                ]),
                2
            ),
            $price
        );
    }

    /**
     * @param array{id: string, name: string, taxRate: float, areaRules?: list<array<string, mixed>>} $tax
     */
    #[DataProvider('dataProviderTestGrossToNet')]
    public function testGrossToNet(array $tax, float $expectedUnitPrice, float $expectedTotalPrice, float $expectedTax): void
    {
        $this->getContainer()->get('tax.repository')->create([$tax], Context::createDefaultContext());

        $price = $this->sendRequest([
            'price' => 11.9,
            'output' => 'net',
            'taxId' => $tax['id'],
            'calculated' => true,
        ]);

        static::assertEquals(
            new CalculatedPrice(
                $expectedUnitPrice,
                $expectedTotalPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $tax['taxRate'], $expectedTotalPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($tax['taxRate'], 100),
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
     * @param array{id: string, name: string, taxRate: float, areaRules?: list<array<string, mixed>>} $tax
     */
    #[DataProvider('dataProviderTestCalculatePricesNetToGross')]
    public function testCalculatePricesNetToGross(array $tax, float $expectedUnitPrice, float $expectedTotalPrice, float $expectedTax): void
    {
        $this->getContainer()->get('tax.repository')->create([$tax], Context::createDefaultContext());

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
            'taxId' => $tax['id'],
        ], $productId, $currencyId);

        static::assertEquals(
            new CalculatedPrice(
                $expectedUnitPrice,
                $expectedTotalPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $tax['taxRate'], $expectedTotalPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($tax['taxRate'], 100),
                ])
            ),
            $price
        );
    }

    /**
     * @param array{id: string, name: string, taxRate: float, areaRules?: list<array<string, mixed>>} $tax
     */
    #[DataProvider('dataProviderTestCalculatePricesNetToNet')]
    public function testCalculatePricesNetToNet(array $tax, float $expectedUnitPrice, float $expectedTotalPrice, float $expectedTax): void
    {
        $this->getContainer()->get('tax.repository')->create([$tax], Context::createDefaultContext());

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
            'taxId' => $tax['id'],
        ], $productId, $currencyId);

        static::assertEquals(
            new CalculatedPrice(
                $expectedUnitPrice,
                $expectedTotalPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $tax['taxRate'], $expectedTotalPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($tax['taxRate'], 100),
                ])
            ),
            $price
        );
    }

    /**
     * @param array{id: string, name: string, taxRate: float, areaRules?: list<array<string, mixed>>} $tax
     */
    #[DataProvider('dataProviderTestCalculatePricesGrossToGross')]
    public function testCalculatePricesGrossToGross(array $tax, float $expectedUnitPrice, float $expectedTotalPrice, float $expectedTax): void
    {
        $this->getContainer()->get('tax.repository')->create([$tax], Context::createDefaultContext());

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
            'taxId' => $tax['id'],
        ], $productId, $currencyId);

        static::assertEquals(
            new CalculatedPrice(
                $expectedUnitPrice,
                $expectedTotalPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $tax['taxRate'], $expectedTotalPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($tax['taxRate'], 100),
                ])
            ),
            $price
        );
    }

    /**
     * @param array{id: string, name: string, taxRate: float, areaRules?: list<array<string, mixed>>} $tax
     */
    #[DataProvider('dataProviderTestCalculatePricesNetToGrossWithQuantity')]
    public function testCalculatePricesNetToGrossWithQuantity(array $tax, float $expectedUnitPrice, float $expectedTotalPrice, float $expectedTax): void
    {
        $this->getContainer()->get('tax.repository')->create([$tax], Context::createDefaultContext());

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
            'taxId' => $tax['id'],
        ], $productId, $currencyId);

        static::assertEquals(
            new CalculatedPrice(
                $expectedUnitPrice,
                $expectedTotalPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $tax['taxRate'], $expectedTotalPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($tax['taxRate'], 100),
                ]),
                2
            ),
            $price
        );
    }

    /**
     * @param array{id: string, name: string, taxRate: float, areaRules?: list<array<string, mixed>>} $tax
     */
    #[DataProvider('dataProviderTestCalculatePricesGrossToGrossWithQuantity')]
    public function testCalculatePricesGrossToGrossWithQuantity(array $tax, float $expectedUnitPrice, float $expectedTotalPrice, float $expectedTax): void
    {
        $this->getContainer()->get('tax.repository')->create([$tax], Context::createDefaultContext());

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
            'taxId' => $tax['id'],
        ], $productId, $currencyId);

        static::assertEquals(
            new CalculatedPrice(
                $expectedUnitPrice,
                $expectedTotalPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $tax['taxRate'], $expectedTotalPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($tax['taxRate'], 100),
                ]),
                2
            ),
            $price
        );
    }

    /**
     * @param array{id: string, name: string, taxRate: float, areaRules?: list<array<string, mixed>>} $tax
     */
    #[DataProvider('dataProviderTestCalculatePricesGrossToNet')]
    public function testCalculatePricesGrossToNet(array $tax, float $expectedUnitPrice, float $expectedTotalPrice, float $expectedTax): void
    {
        $this->getContainer()->get('tax.repository')->create([$tax], Context::createDefaultContext());

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
            'taxId' => $tax['id'],
        ], $productId, $currencyId);

        static::assertEquals(
            new CalculatedPrice(
                $expectedUnitPrice,
                $expectedTotalPrice,
                new CalculatedTaxCollection([
                    new CalculatedTax($expectedTax, $tax['taxRate'], $expectedTotalPrice),
                ]),
                new TaxRuleCollection([
                    new TaxRule($tax['taxRate'], 100),
                ])
            ),
            $price
        );
    }

    public static function dataProviderTestNetToNet(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'tax' => [
                'id' => Uuid::randomHex(),
                'name' => 'NineteenPercentTax',
                'taxRate' => 19,
            ],
            'expectedPrice' => 10.002,
            'expectedTax' => 1.90038,
        ];

        yield 'Case with tax rate 17.1' => [
            'tax' => [
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
            'expectedPrice' => 10.002,
            'expectedTax' => 1.710342,
        ];

        yield 'Case with tax rate 9.74' => [
            'tax' => [
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
            'expectedPrice' => 10.002,
            'expectedTax' => 0.9741948,
        ];

        yield 'Case with tax rate 13.486' => [
            'tax' => [
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
            'expectedPrice' => 10.002,
            'expectedTax' => 1.34886972,
        ];
    }

    public static function dataProviderTestNetToGross(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'tax' => [
                'id' => Uuid::randomHex(),
                'name' => 'NineteenPercentTax',
                'taxRate' => 19,
            ],
            'expectedPrice' => 11.9,
            'expectedTax' => 1.9,
        ];

        yield 'Case with tax rate 17.1' => [
            'tax' => [
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
            'expectedPrice' => 11.71,
            'expectedTax' => 1.71,
        ];

        yield 'Case with tax rate 9.74' => [
            'tax' => [
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
            'expectedPrice' => 10.974,
            'expectedTax' => 0.974,
        ];

        yield 'Case with tax rate 13.486' => [
            'tax' => [
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
            'expectedPrice' => 11.3486,
            'expectedTax' => 1.3486,
        ];
    }

    public static function dataProviderTestGrossToGross(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'tax' => [
                'id' => Uuid::randomHex(),
                'name' => 'NineteenPercentTax',
                'taxRate' => 19,
            ],
            'expectedPrice' => 11.9,
            'expectedTax' => 1.9,
        ];

        yield 'Case with tax rate 17.1' => [
            'tax' => [
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
            'expectedPrice' => 11.9,
            'expectedTax' => 1.7377455166524,
        ];

        yield 'Case with tax rate 9.74' => [
            'tax' => [
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
            'expectedPrice' => 11.9,
            'expectedTax' => 1.0561873519227,
        ];

        yield 'Case with tax rate 13.486' => [
            'tax' => [
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
            'expectedPrice' => 11.9,
            'expectedTax' => 1.4141250903195,
        ];
    }

    public static function dataProviderTestNetToGrossWithQuantity(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'tax' => [
                'id' => Uuid::randomHex(),
                'name' => 'NineteenPercentTax',
                'taxRate' => 19,
            ],
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 23.8,
            'expectedTax' => 3.8,
        ];

        yield 'Case with tax rate 17.1' => [
            'tax' => [
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
            'expectedUnitPrice' => 11.71,
            'expectedTotalPrice' => 23.42,
            'expectedTax' => 3.42,
        ];

        yield 'Case with tax rate 9.74' => [
            'tax' => [
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
            'expectedUnitPrice' => 10.974,
            'expectedTotalPrice' => 21.948,
            'expectedTax' => 1.948,
        ];

        yield 'Case with tax rate 13.486' => [
            'tax' => [
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
            'expectedUnitPrice' => 11.3486,
            'expectedTotalPrice' => 22.6972,
            'expectedTax' => 2.6972,
        ];
    }

    public static function dataProviderTestGrossToGrossWithQuantity(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'tax' => [
                'id' => Uuid::randomHex(),
                'name' => 'NineteenPercentTax',
                'taxRate' => 19,
            ],
            'expectedUnitPrice' => 10,
            'expectedTotalPrice' => 20,
            'expectedTax' => 3.1932773109244,
        ];

        yield 'Case with tax rate 17.1' => [
            'tax' => [
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
            'expectedUnitPrice' => 10,
            'expectedTotalPrice' => 20,
            'expectedTax' => 2.9205807002562,
        ];

        yield 'Case with tax rate 9.74' => [
            'tax' => [
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
            'expectedUnitPrice' => 10,
            'expectedTotalPrice' => 20,
            'expectedTax' => 1.7751047931474,
        ];

        yield 'Case with tax rate 13.486' => [
            'tax' => [
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
            'expectedUnitPrice' => 10,
            'expectedTotalPrice' => 20,
            'expectedTax' => 2.3766808240664,
        ];
    }

    public static function dataProviderTestGrossToNet(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'tax' => [
                'id' => Uuid::randomHex(),
                'name' => 'NineteenPercentTax',
                'taxRate' => 19,
            ],
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 2.261,
        ];

        yield 'Case with tax rate 17.1' => [
            'tax' => [
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
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 2.0349,
        ];

        yield 'Case with tax rate 9.74' => [
            'tax' => [
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
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 1.15906,
        ];

        yield 'Case with tax rate 13.486' => [
            'tax' => [
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
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 1.604834,
        ];
    }

    public static function dataProviderTestCalculatePricesNetToGross(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'tax' => [
                'id' => Uuid::randomHex(),
                'name' => 'NineteenPercentTax',
                'taxRate' => 19,
            ],
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 1.9,
        ];

        yield 'Case with tax rate 17.1' => [
            'tax' => [
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
            'expectedUnitPrice' => 11.71,
            'expectedTotalPrice' => 11.71,
            'expectedTax' => 1.71,
        ];

        yield 'Case with tax rate 9.74' => [
            'tax' => [
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
            'expectedUnitPrice' => 10.974,
            'expectedTotalPrice' => 10.974,
            'expectedTax' => 0.974,
        ];

        yield 'Case with tax rate 13.486' => [
            'tax' => [
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
            'expectedUnitPrice' => 11.3486,
            'expectedTotalPrice' => 11.3486,
            'expectedTax' => 1.3486,
        ];
    }

    public static function dataProviderTestCalculatePricesNetToNet(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'tax' => [
                'id' => Uuid::randomHex(),
                'name' => 'NineteenPercentTax',
                'taxRate' => 19,
            ],
            'expectedUnitPrice' => 10.002,
            'expectedTotalPrice' => 10.002,
            'expectedTax' => 1.9003800000000002,
        ];

        yield 'Case with tax rate 17.1' => [
            'tax' => [
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
            'expectedUnitPrice' => 10.002,
            'expectedTotalPrice' => 10.002,
            'expectedTax' => 1.710342,
        ];

        yield 'Case with tax rate 9.74' => [
            'tax' => [
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
            'expectedUnitPrice' => 10.002,
            'expectedTotalPrice' => 10.002,
            'expectedTax' => 0.9741948,
        ];

        yield 'Case with tax rate 13.486' => [
            'tax' => [
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
            'expectedUnitPrice' => 10.002,
            'expectedTotalPrice' => 10.002,
            'expectedTax' => 1.34886972,
        ];
    }

    public static function dataProviderTestCalculatePricesGrossToGross(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'tax' => [
                'id' => Uuid::randomHex(),
                'name' => 'NineteenPercentTax',
                'taxRate' => 19,
            ],
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 1.9,
        ];

        yield 'Case with tax rate 17.1' => [
            'tax' => [
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
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 1.7377455166524,
        ];

        yield 'Case with tax rate 9.74' => [
            'tax' => [
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
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 1.0561873519227,
        ];

        yield 'Case with tax rate 13.486' => [
            'tax' => [
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
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 1.4141250903195,
        ];
    }

    public static function dataProviderTestCalculatePricesNetToGrossWithQuantity(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'tax' => [
                'id' => Uuid::randomHex(),
                'name' => 'NineteenPercentTax',
                'taxRate' => 19,
            ],
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 23.8,
            'expectedTax' => 3.8,
        ];

        yield 'Case with tax rate 17.1' => [
            'tax' => [
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
            'expectedUnitPrice' => 11.71,
            'expectedTotalPrice' => 23.42,
            'expectedTax' => 3.42,
        ];

        yield 'Case with tax rate 9.74' => [
            'tax' => [
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
            'expectedUnitPrice' => 10.974,
            'expectedTotalPrice' => 21.948,
            'expectedTax' => 1.948,
        ];

        yield 'Case with tax rate 13.486' => [
            'tax' => [
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
            'expectedUnitPrice' => 11.3486,
            'expectedTotalPrice' => 22.6972,
            'expectedTax' => 2.6972,
        ];
    }

    public static function dataProviderTestCalculatePricesGrossToGrossWithQuantity(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'tax' => [
                'id' => Uuid::randomHex(),
                'name' => 'NineteenPercentTax',
                'taxRate' => 19,
            ],
            'expectedUnitPrice' => 10,
            'expectedTotalPrice' => 20,
            'expectedTax' => 3.19327731092437,
        ];

        yield 'Case with tax rate 17.1' => [
            'tax' => [
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
            'expectedUnitPrice' => 10,
            'expectedTotalPrice' => 20,
            'expectedTax' => 2.9205807002562,
        ];

        yield 'Case with tax rate 9.74' => [
            'tax' => [
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
            'expectedUnitPrice' => 10,
            'expectedTotalPrice' => 20,
            'expectedTax' => 1.7751047931474,
        ];

        yield 'Case with tax rate 13.486' => [
            'tax' => [
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
            'expectedUnitPrice' => 10,
            'expectedTotalPrice' => 20,
            'expectedTax' => 2.3766808240664,
        ];
    }

    public static function dataProviderTestCalculatePricesGrossToNet(): \Generator
    {
        yield 'Case with tax rate 19' => [
            'tax' => [
                'id' => Uuid::randomHex(),
                'name' => 'NineteenPercentTax',
                'taxRate' => 19,
            ],
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 2.261,
        ];

        yield 'Case with tax rate 17.1' => [
            'tax' => [
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
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 2.0349,
        ];

        yield 'Case with tax rate 9.74' => [
            'tax' => [
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
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 1.15906,
        ];

        yield 'Case with tax rate 13.486' => [
            'tax' => [
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
            'expectedUnitPrice' => 11.9,
            'expectedTotalPrice' => 11.9,
            'expectedTax' => 1.604834,
        ];
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
}
