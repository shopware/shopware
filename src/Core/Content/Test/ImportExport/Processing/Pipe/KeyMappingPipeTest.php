<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Processing\Pipe;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Processing\Pipe\KeyMappingPipe;
use Shopware\Core\Content\ImportExport\Struct\Config;

class KeyMappingPipeTest extends TestCase
{
    public function testEmptyMapping(): void
    {
        $mapping = [];
        $keyMappingPipe = new KeyMappingPipe($mapping, true);

        $config = new Config($mapping, []);
        $actualOutput = iterator_to_array($keyMappingPipe->in($config, ['foo' => 'bar']));
        static::assertEmpty($actualOutput);

        $actualOutput = iterator_to_array($keyMappingPipe->out($config, ['foo' => 'bar']));
        static::assertEmpty($actualOutput);
    }

    public function simpleMappingProvider(): array
    {
        return [
            [
                'input' => [],
                'expectedOutput' => [
                    'bar' => '',
                    'x' => '',
                ],
            ],
            [
                'input' => [
                    'foo' => 1234,
                ],
                'expectedOutput' => [
                    'bar' => 1234,
                    'x' => '',
                ],
            ],
            [
                'input' => [
                    'a' => 1234,
                ],
                'expectedOutput' => [
                    'bar' => '',
                    'x' => 1234,
                ],
            ],
            [
                'input' => [
                    'foo' => 'test',
                    'a' => 0.1234,
                ],
                'expectedOutput' => [
                    'bar' => 'test',
                    'x' => 0.1234,
                ],
            ],
        ];
    }

    /**
     * @dataProvider simpleMappingProvider
     */
    public function testSimpleMapping(array $input, array $expetedOutput): void
    {
        $mapping = [
            ['key' => 'foo', 'mappedKey' => 'bar'],
            ['key' => 'a', 'mappedKey' => 'x'],
        ];
        $keyMappingPipe = new KeyMappingPipe($mapping, true);

        $config = new Config($mapping, []);

        $actualOutput = iterator_to_array($keyMappingPipe->in($config, $input));

        static::assertSame($expetedOutput, $actualOutput);

        $actualOutput = iterator_to_array($keyMappingPipe->in($config, $keyMappingPipe->out($config, $actualOutput)));

        static::assertSame($expetedOutput, $actualOutput);
    }

    public function nestedProvider(): array
    {
        return [
            [
                'input' => [],
                'expectedOutput' => [
                    'bar' => '',
                    'x_n' => '',
                    'x_y_z1' => '',
                    'x_y_z2' => '',
                ],
            ],
            [
                'input' => [
                    'foo' => 0.123,
                ],
                'expectedOutput' => [
                    'bar' => 0.123,
                    'x_n' => '',
                    'x_y_z1' => '',
                    'x_y_z2' => '',
                ],
            ],
            [
                'input' => [
                    'foo' => 0.123,
                    'a' => [
                        'n' => 'test',
                    ],
                ],
                'expectedOutput' => [
                    'bar' => 0.123,
                    'x_n' => 'test',
                    'x_y_z1' => '',
                    'x_y_z2' => '',
                ],
            ],
            [
                'input' => [
                    'foo' => 0.123,
                    'a' => [
                        'n' => 'test',
                        'b' => [
                            'c1' => 1,
                            'c2' => 2,
                        ],
                    ],
                ],
                'expectedOutput' => [
                    'bar' => 0.123,
                    'x_n' => 'test',
                    'x_y_z1' => 1,
                    'x_y_z2' => 2,
                ],
            ],
        ];
    }

    /**
     * @dataProvider nestedProvider
     */
    public function testFlattenNested(array $input, array $expetedOutput): void
    {
        $mapping = [
            ['key' => 'foo', 'mappedKey' => 'bar'],
            ['key' => 'a.n', 'mappedKey' => 'x_n'],
            ['key' => 'a.b.c1', 'mappedKey' => 'x_y_z1'],
            ['key' => 'a.b.c2', 'mappedKey' => 'x_y_z2'],
        ];
        $keyMappingPipe = new KeyMappingPipe($mapping, true);

        $config = new Config($mapping, []);
        $actualOutput = iterator_to_array($keyMappingPipe->in($config, $input));

        static::assertSame($expetedOutput, $actualOutput);

        $tmp = iterator_to_array($keyMappingPipe->out($config, $actualOutput));
        $actualOutput = iterator_to_array($keyMappingPipe->in($config, $tmp));

        static::assertSame($expetedOutput, $actualOutput);
    }

    public function nestedProviderNoFlatten(): array
    {
        return [
            [
                'input' => [],
                'expectedOutput' => [
                    'bar' => '',
                    'x' => [
                        'n' => '',
                        'y' => [
                            'z1' => '',
                            'z2' => '',
                        ],
                    ],
                ],
            ],
            [
                'input' => [
                    'foo' => 0.123,
                ],
                'expectedOutput' => [
                    'bar' => 0.123,
                    'x' => [
                        'n' => '',
                        'y' => [
                            'z1' => '',
                            'z2' => '',
                        ],
                    ],
                ],
            ],
            [
                'input' => [
                    'foo' => 0.123,
                    'a' => [
                        'n' => 'test',
                    ],
                ],
                'expectedOutput' => [
                    'bar' => 0.123,
                    'x' => [
                        'n' => 'test',
                        'y' => [
                            'z1' => '',
                            'z2' => '',
                        ],
                    ],
                ],
            ],
            [
                'input' => [
                    'foo' => 0.123,
                    'a' => [
                        'n' => 'test',
                        'b' => [
                            'c1' => 1,
                            'c2' => 2,
                        ],
                    ],
                ],
                'expectedOutput' => [
                    'bar' => 0.123,
                    'x' => [
                        'n' => 'test',
                        'y' => [
                            'z1' => 1,
                            'z2' => 2,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider nestedProviderNoFlatten
     */
    public function testNoFlatten(array $input, array $expetedOutput): void
    {
        $mapping = [
            ['key' => 'foo', 'mappedKey' => 'bar'],
            ['key' => 'a.n', 'mappedKey' => 'x.n'],
            ['key' => 'a.b.c1', 'mappedKey' => 'x.y.z1'],
            ['key' => 'a.b.c2', 'mappedKey' => 'x.y.z2'],
        ];
        $keyMappingPipe = new KeyMappingPipe();

        $config = new Config($mapping, [
            'flatten' => false,
        ]);
        $actualOutput = iterator_to_array($keyMappingPipe->in($config, $input));

        static::assertSame($expetedOutput, $actualOutput);

        $tmp = iterator_to_array($keyMappingPipe->out($config, $actualOutput));
        $actualOutput = iterator_to_array($keyMappingPipe->in($config, $tmp));

        static::assertSame($expetedOutput, $actualOutput);
    }
}
