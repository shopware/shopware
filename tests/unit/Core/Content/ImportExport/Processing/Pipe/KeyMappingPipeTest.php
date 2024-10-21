<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Processing\Pipe;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Processing\Pipe\KeyMappingPipe;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(KeyMappingPipe::class)]
class KeyMappingPipeTest extends TestCase
{
    public function testEmptyMapping(): void
    {
        $mapping = [];
        $keyMappingPipe = new KeyMappingPipe($mapping, true);

        $config = new Config($mapping, [], []);
        $pipeInResult = $keyMappingPipe->in($config, ['foo' => 'bar']);
        static::assertInstanceOf(\Traversable::class, $pipeInResult);
        $actualOutput = iterator_to_array($pipeInResult);
        static::assertEmpty($actualOutput);

        $pipeOutResult = $keyMappingPipe->out($config, ['foo' => 'bar']);
        static::assertInstanceOf(\Traversable::class, $pipeOutResult);
        $actualOutput = iterator_to_array($pipeOutResult);
        static::assertEmpty($actualOutput);
    }

    /**
     * @return array<array{input: array<string, mixed>, expectedOutput: array<string, mixed>}>
     */
    public static function simpleMappingProvider(): array
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
     * @param array<string, mixed> $input
     * @param array<string, mixed> $expectedOutput
     */
    #[DataProvider('simpleMappingProvider')]
    public function testSimpleMapping(array $input, array $expectedOutput): void
    {
        $mapping = [
            ['key' => 'foo', 'mappedKey' => 'bar'],
            ['key' => 'a', 'mappedKey' => 'x'],
        ];
        $keyMappingPipe = new KeyMappingPipe($mapping, true);

        $config = new Config($mapping, [], []);

        $pipeInResult = $keyMappingPipe->in($config, $input);
        static::assertInstanceOf(\Traversable::class, $pipeInResult);
        $actualOutput = iterator_to_array($pipeInResult);

        static::assertSame($expectedOutput, $actualOutput);

        $pipeInResult = $keyMappingPipe->in($config, $keyMappingPipe->out($config, $actualOutput));
        static::assertInstanceOf(\Traversable::class, $pipeInResult);
        $actualOutput = iterator_to_array($pipeInResult);

        static::assertSame($expectedOutput, $actualOutput);
    }

    /**
     * @return array<array{input: array<string, mixed>, expectedOutput: array<string, mixed>}>
     */
    public static function nestedProvider(): array
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
     * @param array<string, mixed> $input
     * @param array<string, mixed> $expectedOutput
     */
    #[DataProvider('nestedProvider')]
    public function testFlattenNested(array $input, array $expectedOutput): void
    {
        $mapping = [
            ['key' => 'foo', 'mappedKey' => 'bar'],
            ['key' => 'a.n', 'mappedKey' => 'x_n'],
            ['key' => 'a.b.c1', 'mappedKey' => 'x_y_z1'],
            ['key' => 'a.b.c2', 'mappedKey' => 'x_y_z2'],
        ];
        $keyMappingPipe = new KeyMappingPipe($mapping, true);

        $config = new Config($mapping, [], []);
        $pipeInResult = $keyMappingPipe->in($config, $input);
        static::assertInstanceOf(\Traversable::class, $pipeInResult);
        $actualOutput = iterator_to_array($pipeInResult);

        static::assertSame($expectedOutput, $actualOutput);

        $pipeOutResult = $keyMappingPipe->out($config, $actualOutput);
        static::assertInstanceOf(\Traversable::class, $pipeOutResult);
        $tmp = iterator_to_array($pipeOutResult);
        $pipeInResult = $keyMappingPipe->in($config, $tmp);
        static::assertInstanceOf(\Traversable::class, $pipeInResult);
        $actualOutput = iterator_to_array($pipeInResult);

        static::assertSame($expectedOutput, $actualOutput);
    }

    /**
     * @return array<array{input: array<string, mixed>, expectedOutput: array<string, mixed>}>
     */
    public static function nestedProviderNoFlatten(): array
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
     * @param array<string, mixed> $input
     * @param array<string, mixed> $expectedOutput
     */
    #[DataProvider('nestedProviderNoFlatten')]
    public function testNoFlatten(array $input, array $expectedOutput): void
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
        ], []);
        $pipeInResult = $keyMappingPipe->in($config, $input);
        static::assertInstanceOf(\Traversable::class, $pipeInResult);
        $actualOutput = iterator_to_array($pipeInResult);

        static::assertSame($expectedOutput, $actualOutput);

        $pipeOutResult = $keyMappingPipe->out($config, $actualOutput);
        static::assertInstanceOf(\Traversable::class, $pipeOutResult);
        $tmp = iterator_to_array($pipeOutResult);
        $pipeInResult = $keyMappingPipe->in($config, $tmp);
        static::assertInstanceOf(\Traversable::class, $pipeInResult);
        $actualOutput = iterator_to_array($pipeInResult);

        static::assertSame($expectedOutput, $actualOutput);
    }

    public function testEntityExtensions(): void
    {
        $input = [
            'extensions' => [
                'testExtension' => [
                    'customString' => 'hello world',
                ],
            ],
        ];
        $mapping = [
            ['key' => 'testExtension.customString', 'mappedKey' => 'TestCustomString'],
        ];

        $keyMappingPipe = new KeyMappingPipe();
        $config = new Config($mapping, [], []);
        $pipeInResult = $keyMappingPipe->in($config, $input);
        static::assertInstanceOf(\Traversable::class, $pipeInResult);
        $actualOutput = iterator_to_array($pipeInResult);
        static::assertSame([
            'TestCustomString' => 'hello world',
        ], $actualOutput);
    }

    public function testOutIgnoresRecordsWithoutMapping(): void
    {
        $record = [
            'csv-column-name' => 'value',
            'a' => 'b',
        ];
        $mapping = [
            ['mappedKey' => 'csv-column-name', 'key' => 'db-field'],
        ];

        $keyMappingPipe = new KeyMappingPipe($mapping, true);
        $config = new Config($mapping, [], []);

        $pipeOutResult = $keyMappingPipe->out($config, $record);
        static::assertInstanceOf(\Traversable::class, $pipeOutResult);

        $actualOutput = iterator_to_array($pipeOutResult);
        static::assertSame(['db-field' => 'value'], $actualOutput);
    }
}
