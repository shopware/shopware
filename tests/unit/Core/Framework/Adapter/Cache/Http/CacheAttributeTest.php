<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheAttribute;

/**
 * @phpstan-import-type CacheAttributeArray from \Shopware\Core\Framework\Adapter\Cache\Http\CacheAttribute
 * @phpstan-import-type CacheAttributeType from \Shopware\Core\Framework\Adapter\Cache\Http\CacheAttribute
 *
 * @internal
 */
#[CoversClass(CacheAttribute::class)]
class CacheAttributeTest extends TestCase
{
    /**
     * @param CacheAttributeArray $input
     * @param array<string>|null $expectedStates
     */
    #[DataProvider('fromArrayProvider')]
    public function testFromArray(array $input, ?int $expectedMaxAge, ?int $expectedSMaxAge, ?array $expectedStates): void
    {
        $attribute = CacheAttribute::fromArray($input);

        static::assertSame($expectedMaxAge, $attribute->maxAge);
        static::assertSame($expectedSMaxAge, $attribute->sMaxAge);
        static::assertSame($expectedStates, $attribute->states);
    }

    /**
     * @return \Generator<string, CacheAttributeArray>
     */
    public static function fromArrayProvider(): \Generator
    {
        yield 'with clientMaxAge and sharedMaxAge' => [
            'input' => ['clientMaxAge' => 100, 'sharedMaxAge' => 200, 'states' => ['foo']],
            'expectedMaxAge' => 100,
            'expectedSMaxAge' => 200,
            'expectedStates' => ['foo'],
        ];

        yield 'with legacy maxAge value' => [
            'input' => ['maxAge' => 300],
            'expectedMaxAge' => null,
            'expectedSMaxAge' => 300,
            'expectedStates' => null,
        ];

        yield 'sharedMaxAge takes precedence over maxAge' => [
            'input' => ['sharedMaxAge' => 400, 'maxAge' => 300],
            'expectedMaxAge' => null,
            'expectedSMaxAge' => 400,
            'expectedStates' => null,
        ];

        yield 'empty array' => [
            'input' => [],
            'expectedMaxAge' => null,
            'expectedSMaxAge' => null,
            'expectedStates' => null,
        ];
    }

    #[DataProvider('fromAttributeValueProvider')]
    public function testFromAttributeValue(mixed $input, ?CacheAttribute $expected): void
    {
        $result = CacheAttribute::fromAttributeValue($input);

        if ($expected === null) {
            static::assertNull($result);
        } else {
            static::assertInstanceOf(CacheAttribute::class, $result);
            static::assertSame($expected->maxAge, $result->maxAge);
            static::assertSame($expected->sMaxAge, $result->sMaxAge);
            static::assertSame($expected->policyModifier, $result->policyModifier);
            static::assertSame($expected->states, $result->states);
        }
    }

    /**
     * @return \Generator<string, array{input: CacheAttributeType|false|null, expected: ?CacheAttribute}>
     */
    public static function fromAttributeValueProvider(): \Generator
    {
        yield 'null returns null' => [
            'input' => null,
            'expected' => null,
        ];

        yield 'false returns null' => [
            'input' => false,
            'expected' => null,
        ];

        yield 'true returns empty CacheAttribute' => [
            'input' => true,
            'expected' => new CacheAttribute(),
        ];

        yield 'CacheAttribute instance returns same' => [
            'input' => new CacheAttribute(maxAge: 500, sMaxAge: 1000),
            'expected' => new CacheAttribute(maxAge: 500, sMaxAge: 1000),
        ];

        yield 'array with clientMaxAge' => [
            'input' => ['clientMaxAge' => 600, 'sharedMaxAge' => 1200],
            'expected' => new CacheAttribute(maxAge: 600, sMaxAge: 1200),
        ];

        yield 'array with states' => [
            'input' => ['states' => ['state1', 'state2']],
            'expected' => new CacheAttribute(states: ['state1', 'state2']),
        ];

        yield 'string "true" returns empty CacheAttribute' => [
            'input' => 'true',
            'expected' => new CacheAttribute(),
        ];

        yield 'string "1" returns empty CacheAttribute' => [
            'input' => '1',
            'expected' => new CacheAttribute(),
        ];

        yield 'string "false" returns null' => [
            'input' => 'false',
            'expected' => null,
        ];

        yield 'string "0" returns null' => [
            'input' => '0',
            'expected' => null,
        ];

        yield 'empty string returns null' => [
            'input' => '',
            'expected' => null,
        ];

        yield 'int(0) returns null' => [
            'input' => 0,
            'expected' => null,
        ];

        yield 'int(1) returns empty CacheAttribute' => [
            'input' => 1,
            'expected' => new CacheAttribute(),
        ];
    }
}
