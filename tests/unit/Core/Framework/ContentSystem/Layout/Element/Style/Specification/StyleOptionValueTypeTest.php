<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Specification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StyleOptionValueType::class)]
class StyleOptionValueTypeTest extends TestCase
{
    #[TestDox('exposes type, enum, range, effective maxLength and default')]
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

    #[TestDox('reports the default cap when a string option has no declared maxLength')]
    public function testStringDefaultsMaxLengthToCap(): void
    {
        $valueType = new StyleOptionValueType('string', null, null, null, null);

        static::assertSame(StyleOptionValueType::DEFAULT_STRING_MAX_LENGTH, $valueType->maxLength());
    }

    #[TestDox('uses the declared maxLength when explicitly set on a string option')]
    public function testStringKeepsDeclaredMaxLength(): void
    {
        $valueType = new StyleOptionValueType('string', null, null, 64, null);

        static::assertSame(64, $valueType->maxLength());
    }

    #[TestDox('reports the effective cap in schema when a number option has no declared maxLength')]
    public function testNumberToSchemaReportsEffectiveCap(): void
    {
        $valueType = new StyleOptionValueType('number', null, null, null, null);

        static::assertSame(StyleOptionValueType::DEFAULT_STRING_MAX_LENGTH, $valueType->toSchema()['maxLength']);
    }

    #[TestDox('reports no maxLength for a type that has no length cap')]
    public function testUncappedTypeReportsNoMaxLength(): void
    {
        static::assertNull((new StyleOptionValueType('integer', null, null, null, null))->maxLength());
    }

    #[DataProvider('isPrimitiveProvider')]
    #[TestDox('classifies $type as primitive: $expected')]
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
