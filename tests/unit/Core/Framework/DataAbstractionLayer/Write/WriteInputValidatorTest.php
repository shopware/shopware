<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteInputValidator;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(WriteInputValidator::class)]
class WriteInputValidatorTest extends TestCase
{
    /**
     * @param array<array<string, mixed|null>> $input
     */
    #[DataProvider('invalidWriteInputProvider')]
    public function testInvalidWriteInputs(array $input): void
    {
        static::expectException(DataAbstractionLayerException::class);

        WriteInputValidator::validate($input);
    }

    public static function invalidWriteInputProvider(): \Generator
    {
        yield 'associative array' => [
            'input' => [
                'id' => ['id' => Uuid::randomHex()],
            ],
        ];

        yield 'array of non associative array' => [
            'input' => [
                [Uuid::randomHex()],
            ],
        ];

        yield 'array of empty arrays' => [
            'input' => [
                [],
                [],
            ],
        ];

        yield 'array with int keys not starting with 0' => [
            'input' => [
                2 => ['id' => Uuid::randomHex()],
                3 => ['id' => Uuid::randomHex()],
            ],
        ];

        yield 'array with int keys not in order' => [
            'input' => [
                1 => ['id' => Uuid::randomHex()],
                0 => ['id' => Uuid::randomHex()],
            ],
        ];

        yield 'array with mixed keys' => [
            'input' => [
                0 => ['id' => Uuid::randomHex()],
                'two' => ['id' => Uuid::randomHex()],
            ],
        ];

        yield 'array with non consecutive keys' => [
            'input' => [
                1 => ['id' => Uuid::randomHex()],
                0 => ['id' => Uuid::randomHex()],
            ],
        ];

        yield 'array of array with int keys not starting with 0' => [
            'input' => [
                [1 => Uuid::randomHex(), Uuid::randomHex()],
            ],
        ];

        yield 'array of array with int keys not in order' => [
            'input' => [
                [1 => Uuid::randomHex(), 0 => Uuid::randomHex()],
            ],
        ];

        yield 'array of array with mixed keys' => [
            'input' => [
                [0 => Uuid::randomHex(), 'id' => Uuid::randomHex()],
            ],
        ];

        yield 'array of array with non consecutive keys' => [
            'input' => [
                [0 => Uuid::randomHex(), 2 => Uuid::randomHex()],
            ],
        ];
    }

    /**
     * @param array<array<string, mixed|null>> $input
     */
    #[DataProvider('validWriteInputProvider')]
    #[DoesNotPerformAssertions]
    public function testValidWriteInputs(array $input): void
    {
        WriteInputValidator::validate($input);
    }

    public static function validWriteInputProvider(): \Generator
    {
        yield 'empty array' => [
            'input' => [],
        ];

        yield 'associative array' => [
            'input' => [
                ['id' => Uuid::randomHex()],
            ],
        ];

        yield 'array of associative arrays' => [
            'input' => [
                ['id' => Uuid::randomHex()],
                ['id' => Uuid::randomHex()],
            ],
        ];
    }
}
