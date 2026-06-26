<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Specification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;

/**
 * @internal
 */
#[CoversClass(StyleOptionValueType::class)]
class StyleOptionValueTypeTest extends TestCase
{
    #[TestDox('toSchema exposes type, enum, range, effective maxLength and default')]
    public function testToSchemaExposesAllFacets(): void
    {
        $valueType = new StyleOptionValueType('integer', [1, 2, 3], ['min' => 1, 'max' => 12], null, 2);

        static::assertSame(
            [
                'type' => 'integer',
                'enum' => [1, 2, 3],
                'range' => ['min' => 1, 'max' => 12],
                'maxLength' => null,
                'default' => 2,
            ],
            $valueType->toSchema(),
        );
    }

    #[TestDox('a string option without a declared maxLength reports the default cap')]
    public function testStringDefaultsMaxLengthToCap(): void
    {
        $valueType = new StyleOptionValueType('string', null, null, null, null);

        static::assertSame(StyleOptionValueType::DEFAULT_STRING_MAX_LENGTH, $valueType->maxLength());
    }

    #[TestDox('a string option uses its declared maxLength when given')]
    public function testStringKeepsDeclaredMaxLength(): void
    {
        $valueType = new StyleOptionValueType('string', null, null, 64, null);

        static::assertSame(64, $valueType->maxLength());
    }

    #[TestDox('a number option without a declared maxLength reports the effective cap in its schema')]
    public function testNumberToSchemaReportsEffectiveCap(): void
    {
        $valueType = new StyleOptionValueType('number', null, null, null, null);

        static::assertSame(StyleOptionValueType::DEFAULT_STRING_MAX_LENGTH, $valueType->toSchema()['maxLength']);
    }

    #[DataProvider('uncappedTypeProvider')]
    #[TestDox('a $type option reports no maxLength')]
    public function testUncappedTypesReportNoMaxLength(string $type): void
    {
        static::assertNull((new StyleOptionValueType($type, null, null, null, null))->maxLength());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function uncappedTypeProvider(): iterable
    {
        yield 'integer' => ['integer'];
        yield 'boolean' => ['boolean'];
    }

    #[DataProvider('isPrimitiveProvider')]
    #[TestDox('isPrimitive reports $type as $expected')]
    public function testIsPrimitive(string $type, bool $expected): void
    {
        static::assertSame($expected, (new StyleOptionValueType($type, null, null, null, null))->isPrimitive());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function isPrimitiveProvider(): iterable
    {
        yield 'string' => ['string', true];
        yield 'integer' => ['integer', true];
        yield 'number' => ['number', true];
        yield 'boolean' => ['boolean', true];
        yield 'object (FQCN placeholder)' => ['object', false];
        yield 'empty' => ['', false];
    }
}
