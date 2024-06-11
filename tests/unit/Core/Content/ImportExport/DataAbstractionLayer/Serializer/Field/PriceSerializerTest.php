<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\PriceSerializer;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(PriceSerializer::class)]
class PriceSerializerTest extends TestCase
{
    /**
     * @param Price|array<string, array<string, mixed>>|null $prices
     * @param array<string, array<string, mixed>>|null $expected
     */
    #[DataProvider('unserializedPrices')]
    public function testSerializePrices(PriceField $field, Price|array|null $prices, ?array $expected): void
    {
        $currencyRepository = $this->getCurrencyRepository([
            new CurrencyCollection([
                (new CurrencyEntity())->assign([
                    'id' => Defaults::CURRENCY,
                    'isoCode' => 'EUR',
                ]),
            ]),
        ]);

        $priceSerializer = new PriceSerializer($currencyRepository);
        $config = new Config([], [], []);

        $result = \iterator_to_array($priceSerializer->serialize($config, $field, $prices));
        static::assertSame($expected, $result);
    }

    /**
     * @param array<string, array<string, mixed>>|null $serializedPrices
     * @param array<string, array<string, mixed>>|null $expected
     */
    #[DataProvider('serializedPrices')]
    public function testDeserializePrices(PriceField $field, ?array $serializedPrices, ?array $expected): void
    {
        $currencyRepository = $this->getCurrencyRepository([[Defaults::CURRENCY]]);

        $priceSerializer = new PriceSerializer($currencyRepository);
        $config = new Config([], [], []);

        $result = $priceSerializer->deserialize($config, $field, $serializedPrices);
        static::assertSame($expected, $result);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function unserializedPrices(): array
    {
        return [
            'empty prices' => [
                'field' => new PriceField('price', 'price'),
                'prices' => [],
                'expected' => [],
            ],
            'valid price #1' => [
                'field' => new PriceField('price', 'price'),
                'prices' => [new Price(Defaults::CURRENCY, 10.0, 10.0, false)],
                'expected' => [
                    'price' => [
                        'EUR' => [
                            'extensions' => [],
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 10.0,
                            'gross' => 10.0,
                            'linked' => false,
                            'listPrice' => null,
                            'percentage' => null,
                            'regulationPrice' => null,
                        ],
                        'DEFAULT' => [
                            'extensions' => [],
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 10.0,
                            'gross' => 10.0,
                            'linked' => false,
                            'listPrice' => null,
                            'percentage' => null,
                            'regulationPrice' => null,
                        ],
                    ],
                ],
            ],
            'invalid price #2' => [
                'field' => new PriceField('price', 'price'),
                'prices' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'gross' => 11,
                        'net' => 10,
                    ],
                ],
                'expected' => [
                    'price' => [
                        'EUR' => [
                            'currencyId' => Defaults::CURRENCY,
                            'gross' => 11,
                            'net' => 10,
                        ],
                        'DEFAULT' => [
                            'currencyId' => Defaults::CURRENCY,
                            'gross' => 11,
                            'net' => 10,
                        ],
                    ],
                ],
            ],
            'valid list price #1' => [
                'field' => new PriceField('price', 'price'),
                'prices' => [
                    new Price(
                        Defaults::CURRENCY,
                        10.0,
                        10.0,
                        false,
                        new Price(Defaults::CURRENCY, 11.0, 11.0, false)
                    ),
                ],
                'expected' => [
                    'price' => [
                        'EUR' => [
                            'extensions' => [],
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 10.0,
                            'gross' => 10.0,
                            'linked' => false,
                            'listPrice' => [
                                'extensions' => [],
                                'currencyId' => Defaults::CURRENCY,
                                'net' => 11.0,
                                'gross' => 11.0,
                                'linked' => false,
                                'listPrice' => null,
                                'percentage' => null,
                                'regulationPrice' => null,
                            ],
                            'percentage' => null,
                            'regulationPrice' => null,
                        ],
                        'DEFAULT' => [
                            'extensions' => [],
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 10.0,
                            'gross' => 10.0,
                            'linked' => false,
                            'listPrice' => [
                                'extensions' => [],
                                'currencyId' => Defaults::CURRENCY,
                                'net' => 11.0,
                                'gross' => 11.0,
                                'linked' => false,
                                'listPrice' => null,
                                'percentage' => null,
                                'regulationPrice' => null,
                            ],
                            'percentage' => null,
                            'regulationPrice' => null,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function serializedPrices(): array
    {
        return [
            'empty prices' => [
                'field' => new PriceField('price', 'price'),
                'serializedPrices' => [],
                'expected' => null,
            ],
            'valid price #1' => [
                'field' => new PriceField('price', 'price'),
                'serializedPrices' => [
                    'EUR' => [
                        'currencyId' => Defaults::CURRENCY,
                        'gross' => 11,
                        'net' => 10,
                    ],
                    'DEFAULT' => [
                        'currencyId' => Defaults::CURRENCY,
                        'gross' => 11,
                        'net' => 10,
                    ],
                ],
                'expected' => [
                    Defaults::CURRENCY => [
                        'extensions' => [],
                        'currencyId' => Defaults::CURRENCY,
                        'net' => 10.0,
                        'gross' => 11.0,
                        'linked' => false,
                        'listPrice' => null,
                        'percentage' => null,
                        'regulationPrice' => null,
                    ],
                ],
            ],
            'valid price #2' => [
                'field' => new PriceField('price', 'price'),
                'serializedPrices' => [
                    'DEFAULT' => [
                        'net' => '124.234',
                        'gross' => '122.798',
                        'linked' => '0',
                    ],
                ],
                'expected' => [
                    Defaults::CURRENCY => [
                        'extensions' => [],
                        'currencyId' => Defaults::CURRENCY,
                        'net' => 124.234,
                        'gross' => 122.798,
                        'linked' => false,
                        'listPrice' => null,
                        'percentage' => null,
                        'regulationPrice' => null,
                    ],
                ],
            ],
            'valid price #3' => [
                'field' => new PriceField('price', 'price'),
                'serializedPrices' => [
                    'DEFAULT' => [
                        'net' => '124',
                        'gross' => '122',
                        'linked' => '0',
                    ],
                ],
                'expected' => [
                    Defaults::CURRENCY => [
                        'extensions' => [],
                        'currencyId' => Defaults::CURRENCY,
                        'net' => 124.0,
                        'gross' => 122.0,
                        'linked' => false,
                        'listPrice' => null,
                        'percentage' => null,
                        'regulationPrice' => null,
                    ],
                ],
            ],
            'valid price #4' => [
                'field' => new PriceField('price', 'price'),
                'serializedPrices' => [
                    'DEFAULT' => [
                        'net' => '0',
                        'gross' => '0',
                        'linked' => '0',
                    ],
                ],
                'expected' => [
                    Defaults::CURRENCY => [
                        'extensions' => [],
                        'currencyId' => Defaults::CURRENCY,
                        'net' => 0.0,
                        'gross' => 0.0,
                        'linked' => false,
                        'listPrice' => null,
                        'percentage' => null,
                        'regulationPrice' => null,
                    ],
                ],
            ],
            'valid list price #1' => [
                'field' => new PriceField('price', 'price'),
                'serializedPrices' => [
                    'EUR' => [
                        'extensions' => [],
                        'currencyId' => Defaults::CURRENCY,
                        'net' => 10.0,
                        'gross' => 10.0,
                        'linked' => false,
                        'listPrice' => [
                            'extensions' => [],
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 11.0,
                            'gross' => 11.0,
                            'linked' => false,
                            'listPrice' => null,
                            'percentage' => null,
                            'regulationPrice' => null,
                        ],
                        'percentage' => null,
                        'regulationPrice' => null,
                    ],
                    'DEFAULT' => [
                        'extensions' => [],
                        'currencyId' => Defaults::CURRENCY,
                        'net' => 10.0,
                        'gross' => 10.0,
                        'linked' => false,
                        'listPrice' => [
                            'extensions' => [],
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 11.0,
                            'gross' => 11.0,
                            'linked' => false,
                            'listPrice' => null,
                            'percentage' => null,
                            'regulationPrice' => null,
                        ],
                        'percentage' => null,
                        'regulationPrice' => null,
                    ],
                ],
                'expected' => [
                    Defaults::CURRENCY => [
                        'extensions' => [],
                        'currencyId' => Defaults::CURRENCY,
                        'net' => 10.0,
                        'gross' => 10.0,
                        'linked' => false,
                        'listPrice' => [
                            'extensions' => [],
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 11.0,
                            'gross' => 11.0,
                            'linked' => false,
                            'listPrice' => null,
                            'percentage' => null,
                            'regulationPrice' => null,
                        ],
                        'percentage' => null,
                        'regulationPrice' => null,
                    ],
                ],
            ],
            'invalid price #1' => [
                'field' => new PriceField('price', 'price'),
                'serializedPrices' => [
                    'DEFAULT' => [
                        'gross' => '',
                        'net' => '',
                    ],
                ],
                'expected' => null,
            ],
            'invalid price #2' => [
                'field' => new PriceField('price', 'price'),
                'serializedPrices' => [
                    'DEFAULT' => [
                        'gross' => '    ',
                        'net' => '6.5',
                    ],
                ],
                'expected' => null,
            ],
            'invalid price #3' => [
                'field' => new PriceField('price', 'price'),
                'serializedPrices' => [
                    'DEFAULT' => [
                        'gross' => '124.234',
                    ],
                ],
                'expected' => null,
            ],
            'invalid price #4' => [
                'field' => new PriceField('price', 'price'),
                'serializedPrices' => [
                    'DEFAULT' => [
                        'net' => '124.234',
                    ],
                ],
                'expected' => null,
            ],
        ];
    }

    /**
     * @param array<CurrencyCollection<CurrencyEntity>|array<string>> $results
     */
    private function getCurrencyRepository(array $results): EntityRepository
    {
        return new StaticEntityRepository($results, new CurrencyDefinition());
    }
}
