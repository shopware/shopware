<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Codec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementWiringDecoder;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Breakpoint;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\Log\Package;

/**
 * The structural strictness tier of decode: a container, key, id or value whose shape the stored model cannot
 * represent fails decode outright rather than being repaired or dropped. Some rows reach {@see StoredElementWiringDecoder}
 * through the codec's own delegation; that class's own coverage lives in its dedicated test file.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredElementCodec::class)]
class StoredElementCodecStructuralDecodeTest extends StoredElementCodecTestCase
{
    #[TestDox('rejects a top-level key the element wire shape does not carry')]
    public function testDecodeRejectsAnUnknownTopLevelKey(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('element', 'only known element keys', 'unknown key "elements"')
        );

        $this->codec()->decode([
            'id' => 'el-1',
            'component' => 'core:text',
            'properties' => [],
            'elements' => [],
        ]);
    }

    #[DataProvider('rejectedElementIdProvider')]
    #[TestDox('rejects $_dataName as an element id')]
    public function testDecodeRejectsIdsOutsideTheValueDomain(string $id, ContentSystemException $expected): void
    {
        $this->expectExceptionObject($expected);

        $this->codec()->decode(['id' => $id, 'component' => 'core:text', 'properties' => []]);
    }

    /**
     * @param array<array-key, mixed> $wire
     */
    #[DataProvider('rejectsNumericWiringKeysProvider')]
    #[TestDox('rejects $_dataName')]
    public function testDecodeRejectsNumericWiringKeys(array $wire, ContentSystemException $expected): void
    {
        $this->expectExceptionObject($expected);

        $this->codec()->decode($wire);
    }

    /**
     * @param array<string, mixed> $wire
     */
    #[DataProvider('rejectsNestingPastTheLimitProvider')]
    #[TestDox('rejects $_dataName')]
    public function testDecodeRejectsNestingPastTheLimit(array $wire, ContentSystemException $expected): void
    {
        $this->expectExceptionObject($expected);

        $this->codec()->decode($wire);
    }

    /**
     * @param array<string, mixed> $wire
     */
    #[DataProvider('throwsForStructuralDefectProvider')]
    #[TestDox('throws for $_dataName')]
    public function testDecodeThrowsForAStructuralDefect(array $wire, ContentSystemException $expected): void
    {
        $this->expectExceptionObject($expected);

        $this->codec()->decode($wire);
    }

    /**
     * @param array<array-key, mixed> $style
     */
    #[DataProvider('rejectsMalformedStyleProvider')]
    #[TestDox('rejects $_dataName')]
    public function testDecodeRejectsMalformedStyle(array $style, ContentSystemException $expected): void
    {
        $this->expectExceptionObject($expected);

        $this->codec()->decode(self::baseWire(['style' => $style]));
    }

    /**
     * @return iterable<string, array{string, ContentSystemException}>
     */
    public static function rejectedElementIdProvider(): iterable
    {
        yield 'the reserved virtual-root literal' => [
            VirtualRootWrapper::VIRTUAL_ROOT_ID,
            ContentSystemException::invalidElementId(VirtualRootWrapper::VIRTUAL_ROOT_ID, 'it is the reserved virtual-root id'),
        ];

        yield 'the integer-castable string "0"' => [
            '0',
            ContentSystemException::invalidElementId('0', 'PHP casts it to an integer array key'),
        ];

        yield 'the integer-castable string "12"' => [
            '12',
            ContentSystemException::invalidElementId('12', 'PHP casts it to an integer array key'),
        ];

        yield 'the integer-castable string "-3"' => [
            '-3',
            ContentSystemException::invalidElementId('-3', 'PHP casts it to an integer array key'),
        ];
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, ContentSystemException}>
     */
    public static function rejectsNumericWiringKeysProvider(): iterable
    {
        yield 'a numeric-string property key arriving over the wire' => [
            // PHP turns the JSON member name "12" into an integer array key, so the numeric-string case
            // reaches the element constructor as the integer case
            (array) json_decode('{"id":"el-1","component":"core:text","properties":{"12":"x"}}', true, 512, \JSON_THROW_ON_ERROR),
            ContentSystemException::invalidMapKey('Element property map', 'int'),
        ];

        yield 'an integer data requirement key' => [
            [
                'id' => 'el-1',
                'component' => 'core:text',
                'properties' => [],
                'dataRequirements' => [
                    7 => ['source' => 'entity', 'config' => ['entity' => 'product', 'property' => 'productId']],
                ],
            ],
            ContentSystemException::invalidMapKey('Element data requirement map', 'int'),
        ];

        yield 'an integer slot key' => [
            [
                'id' => 'el-1',
                'component' => 'core:section',
                'properties' => [],
                'slots' => [3 => [['id' => 'child-1', 'component' => 'core:text', 'properties' => []]]],
            ],
            ContentSystemException::invalidMapKey('Element slot map', 'int'),
        ];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, ContentSystemException}>
     */
    public static function rejectsNestingPastTheLimitProvider(): iterable
    {
        yield 'an element chain one level past the nesting limit' => [
            self::nestedElements(52),
            ContentSystemException::invalidFieldValueType('slots', 'element nesting at most 50 levels deep', 'deeper nesting'),
        ];

        yield 'a property payload one level past the nesting limit' => [
            self::elementWithNestedValue(52),
            ContentSystemException::invalidFieldValueType(
                'properties[deep]' . str_repeat('[0]', 51),
                'value nesting at most 50 levels deep',
                'deeper nesting'
            ),
        ];
    }

    /**
     * Pins every deliberate strictness divergence: a structural container that the replaced code tolerated
     * (or silently defaulted) now fails decode instead of admitting a shape the codec cannot represent.
     *
     * @return iterable<string, array{array<string, mixed>, ContentSystemException}>
     */
    public static function throwsForStructuralDefectProvider(): iterable
    {
        yield 'a non-array dataRequirements' => [
            self::baseWire(['dataRequirements' => 'not-an-array']),
            ContentSystemException::invalidFieldValueType('dataRequirements', 'array', 'string'),
        ];

        yield 'a non-array slots' => [
            self::baseWire(['slots' => 'not-an-array']),
            ContentSystemException::invalidFieldValueType('slots', 'array', 'string'),
        ];

        yield 'a non-array providesContext' => [
            self::baseWire(['providesContext' => 'not-an-array']),
            ContentSystemException::invalidFieldValueType('providesContext', 'array', 'string'),
        ];

        yield 'a non-array acceptsContext' => [
            self::baseWire(['acceptsContext' => 'not-an-array']),
            ContentSystemException::invalidFieldValueType('acceptsContext', 'array', 'string'),
        ];

        yield 'a non-array style' => [
            self::baseWire(['style' => 'not-an-array']),
            ContentSystemException::invalidFieldValueType('style', 'array', 'string'),
        ];

        yield 'a non-array attributedSpecifications' => [
            self::baseWire(['attributedSpecifications' => 'not-an-array']),
            ContentSystemException::invalidFieldValueType('attributedSpecifications', 'array', 'string'),
        ];

        yield 'a non-string key inside providesContext' => [
            self::baseWire([
                'providesContext' => [0 => ['type' => 'single', 'distribution' => 'keyed', 'keyProperty' => 'sku']],
            ]),
            ContentSystemException::invalidMapKey('Element context provider map', 'int'),
        ];

        yield 'a non-array provider config inside providesContext' => [
            self::baseWire(['providesContext' => ['product' => 'not-an-array']]),
            ContentSystemException::invalidFieldValueType('providesContext[product]', 'array', 'string'),
        ];

        yield 'a non-array consumer config inside acceptsContext' => [
            self::baseWire(['acceptsContext' => ['items' => 'not-an-array']]),
            ContentSystemException::invalidFieldValueType('acceptsContext[items]', 'array', 'string'),
        ];

        yield 'an unparseable context-type enum string' => [
            self::baseWire([
                'providesContext' => ['product' => ['type' => 'bogus-type', 'distribution' => 'keyed', 'keyProperty' => 'sku']],
            ]),
            ContentSystemException::invalidFieldValueType('providesContext[product].type', implode('|', ContextType::values()), 'string'),
        ];

        yield 'an unparseable distribution-strategy enum string' => [
            self::baseWire([
                'providesContext' => ['product' => ['type' => 'single', 'distribution' => 'bogus-strategy']],
            ]),
            ContentSystemException::invalidFieldValueType('providesContext[product].distribution', implode('|', DistributionStrategy::values()), 'string'),
        ];

        yield 'a consumer entry missing type' => [
            self::baseWire(['acceptsContext' => ['items' => ['required' => true]]]),
            ContentSystemException::invalidFieldValueType('acceptsContext[items].type', implode('|', ContextType::values()), 'null'),
        ];

        yield 'a consumer entry missing required' => [
            self::baseWire(['acceptsContext' => ['items' => ['type' => 'single']]]),
            ContentSystemException::invalidFieldValueType('acceptsContext[items].required', 'bool', 'null'),
        ];

        yield 'an unknown key inside a data requirement entry' => [
            self::baseWire(['dataRequirements' => [
                'products' => [
                    'source' => 'entity',
                    'config' => ['entity' => 'product', 'property' => 'productId'],
                    'limit' => 5,
                ],
            ]]),
            ContentSystemException::invalidFieldValueType(
                'dataRequirements[products]',
                'only known data requirement keys',
                'unknown key "limit"'
            ),
        ];

        yield 'an unknown key inside a consumer entry' => [
            self::baseWire(['acceptsContext' => [
                'items' => ['type' => 'single', 'required' => true, 'fallback' => 'none'],
            ]]),
            ContentSystemException::invalidFieldValueType(
                'acceptsContext[items]',
                'only known consumer keys',
                'unknown key "fallback"'
            ),
        ];

        yield 'an associative slot-children array' => [
            self::baseWire([
                'slots' => ['main' => ['a' => ['id' => 'child-1', 'component' => 'core:text', 'properties' => []]]],
            ]),
            ContentSystemException::invalidFieldValueType('slots[main]', 'list of elements', 'array'),
        ];
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, ContentSystemException}>
     */
    public static function rejectsMalformedStyleProvider(): iterable
    {
        yield 'a non-string style option name' => [
            [0 => ['md' => 1]],
            ContentSystemException::invalidMapKey('Element style map', 'int'),
        ];

        yield 'a style value that is neither scalar nor array' => [
            ['null-option' => null],
            ContentSystemException::invalidFieldValueType('style[null-option]', 'scalar or breakpoint map', 'null'),
        ];

        yield 'an explicitly empty breakpoint map' => [
            ['empty-option' => []],
            ContentSystemException::invalidFieldValueType('style[empty-option]', 'a breakpoint map holding at least one breakpoint', 'empty map'),
        ];

        yield 'an unknown breakpoint key' => [
            ['breakpoint-option' => ['bogus-breakpoint' => 5]],
            ContentSystemException::unknownStyleBreakpoint('breakpoint-option', 'bogus-breakpoint', Breakpoint::values()),
        ];

        yield 'a non-scalar breakpoint value' => [
            ['breakpoint-option' => ['md' => ['nested' => 1]]],
            ContentSystemException::invalidFieldValueType('style[breakpoint-option][md]', 'scalar', 'array'),
        ];
    }
}
