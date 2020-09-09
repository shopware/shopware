<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ListingPriceFieldSerializer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class ListingPriceFieldSerializerTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var ListingPriceFieldSerializer
     */
    protected $serializer;

    public function setUp(): void
    {
        $this->serializer = $this->getContainer()->get(ListingPriceFieldSerializer::class);
    }

    /**
     * @dataProvider decodeProvider
     */
    public function testDecode(array $array, $expected): void
    {
        $result = $this->serializer->decode(new PriceField('test', 'test'), json_encode($array));
        static::assertEquals($expected, json_decode(json_encode($result), true));
    }

    public function decodeProvider(): iterable
    {
        // Deprecated values
        yield [
            [
                'structs' => 'test',
            ],
            [],
        ];

        // String as int
        yield [
            [
                'default' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'from' => [
                            'net' => '5',
                            'gross' => '5',
                            'linked' => true,
                        ],
                        'to' => [
                            'net' => '5',
                            'gross' => '5',
                            'linked' => true,
                        ],
                    ],
                ],
            ],
            [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'ruleId' => null,
                    'extensions' => [],
                    'from' => [
                        'net' => 5,
                        'gross' => 5,
                        'linked' => true,
                        'currencyId' => '',
                        'listPrice' => null,
                        'extensions' => [],
                    ],
                    'to' => [
                        'net' => 5,
                        'gross' => 5,
                        'linked' => true,
                        'currencyId' => '',
                        'listPrice' => null,
                        'extensions' => [],
                    ],
                ],
            ],
        ];

        // String as float
        yield [
            [
                'default' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'from' => [
                            'net' => '5.7',
                            'gross' => '5.7',
                            'linked' => true,
                        ],
                        'to' => [
                            'net' => '5.7',
                            'gross' => '5.7',
                            'linked' => true,
                        ],
                    ],
                ],
            ],
            [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'ruleId' => null,
                    'extensions' => [],
                    'from' => [
                        'net' => 5.7,
                        'gross' => 5.7,
                        'linked' => true,
                        'currencyId' => '',
                        'listPrice' => null,
                        'extensions' => [],
                    ],
                    'to' => [
                        'net' => 5.7,
                        'gross' => 5.7,
                        'linked' => true,
                        'currencyId' => '',
                        'listPrice' => null,
                        'extensions' => [],
                    ],
                ],
            ],
        ];

        // As Int should stay int
        yield [
            [
                'default' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'from' => [
                            'net' => 5,
                            'gross' => 5,
                            'linked' => true,
                        ],
                        'to' => [
                            'net' => 5,
                            'gross' => 5,
                            'linked' => true,
                        ],
                    ],
                ],
            ],
            [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'ruleId' => null,
                    'extensions' => [],
                    'from' => [
                        'net' => 5,
                        'gross' => 5,
                        'linked' => true,
                        'currencyId' => '',
                        'listPrice' => null,
                        'extensions' => [],
                    ],
                    'to' => [
                        'net' => 5,
                        'gross' => 5,
                        'linked' => true,
                        'currencyId' => '',
                        'listPrice' => null,
                        'extensions' => [],
                    ],
                ],
            ],
        ];

        // As Float should stay float
        yield [
            [
                'default' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'from' => [
                            'net' => 5.7,
                            'gross' => 5.7,
                            'linked' => true,
                        ],
                        'to' => [
                            'net' => 5.7,
                            'gross' => 5.7,
                            'linked' => true,
                        ],
                    ],
                ],
            ],
            [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'ruleId' => null,
                    'extensions' => [],
                    'from' => [
                        'net' => 5.7,
                        'gross' => 5.7,
                        'linked' => true,
                        'currencyId' => '',
                        'listPrice' => null,
                        'extensions' => [],
                    ],
                    'to' => [
                        'net' => 5.7,
                        'gross' => 5.7,
                        'linked' => true,
                        'currencyId' => '',
                        'listPrice' => null,
                        'extensions' => [],
                    ],
                ],
            ],
        ];
    }
}
