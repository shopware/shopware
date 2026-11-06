<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Breakpoint;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueOrigin;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StubStruct;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RenderedElement::class)]
class RenderedElementTest extends TestCase
{
    /**
     * @return iterable<string, array{callable(RenderedElement): RenderedElement, callable(RenderedElement): mixed, mixed}>
     */
    public static function withMethodProvider(): iterable
    {
        yield 'withProperty' => [
            static fn (RenderedElement $element): RenderedElement => $element->withProperty('headline', 'changed'),
            static fn (RenderedElement $element): array => $element->properties,
            ['headline' => 'changed'],
        ];
        yield 'withProperties' => [
            static fn (RenderedElement $element): RenderedElement => $element->withProperties(['tagline' => 'new']),
            static fn (RenderedElement $element): array => $element->properties,
            ['tagline' => 'new'],
        ];
        yield 'withSlots' => [
            static fn (RenderedElement $element): RenderedElement => $element->withSlots([
                'main' => [new RenderedElement('child-1', 'core:text')],
            ]),
            static fn (RenderedElement $element): array => $element->slots,
            ['main' => [new RenderedElement('child-1', 'core:text')]],
        ];
    }

    /**
     * The declared slot shape is `array<string, list<RenderedElement>>`; every case here is a caller
     * ignoring that declaration, which the native `array` type cannot stop on its own. One row per
     * rejection branch: the numeric name, the non-list value, the keyed-array value and the child that
     * is not a rendered element.
     *
     * @return iterable<string, array{array<array-key, mixed>, ContentSystemException}>
     */
    public static function malformedSlotMapProvider(): iterable
    {
        yield 'numeric slot name' => [
            ['0' => [new RenderedElement('child-1', 'core:text')]],
            ContentSystemException::invalidMapKey('Rendered element slot map', 'int'),
        ];
        yield 'slot holding no array at all' => [
            ['main' => null],
            ContentSystemException::invalidMapValue('Rendered element slot map', 'main', 'list', 'null'),
        ];
        yield 'slot holding a keyed array instead of a list' => [
            ['main' => ['first' => new RenderedElement('child-1', 'core:text')]],
            ContentSystemException::invalidMapValue('Rendered element slot map', 'main', 'list', 'array'),
        ];
        yield 'slot child that is not a rendered element' => [
            ['main' => ['child-1']],
            ContentSystemException::invalidMapValue(
                'Rendered element slot child list',
                'main',
                RenderedElement::class,
                'string'
            ),
        ];
    }

    /**
     * A rendered property key may come from an element-type declaration or from a context consumer's
     * `propertyAlias`, neither of which is checked upstream, so the ban is re-stated at this boundary.
     * PHP casts a numeric string key to an integer before the constructor ever sees it, so the integer
     * key is the only shape that reaches the check.
     *
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function numericPropertyKeyProvider(): iterable
    {
        yield 'integer key' => [[0 => 'Hello']];
    }

    /**
     * One row per admission branch of the permitted property value domain: the scalar test, the null
     * test, and each of the three `instanceof` operands. `\DateTimeInterface` and `\BackedEnum` are
     * operands of their own because the bar is concealment rather than objecthood: neither can hold a
     * `Struct` in its object graph, so neither can carry one past the response encoders' protection gate.
     *
     * @return iterable<string, array{mixed}>
     */
    public static function permittedPropertyValueProvider(): iterable
    {
        yield 'scalar' => ['Hello'];
        yield 'null' => [null];
        yield 'struct' => [new StubStruct()];
        yield 'date time' => [new \DateTimeImmutable('2026-01-01 12:00:00')];
        yield 'backed enum' => [Breakpoint::Md];
    }

    /**
     * @return iterable<string, array{callable(RenderedElement): RenderedElement, callable(RenderedElement): array<array-key, mixed>}>
     */
    public static function unnamedFieldProvider(): iterable
    {
        yield 'withProperty leaves the slot map' => [
            static fn (RenderedElement $element): RenderedElement => $element->withProperty('headline', 'Changed'),
            static fn (RenderedElement $element): array => $element->slots,
        ];
        yield 'withProperties leaves the slot map' => [
            static fn (RenderedElement $element): RenderedElement => $element->withProperties(['headline' => 'Changed']),
            static fn (RenderedElement $element): array => $element->slots,
        ];
        yield 'withSlots leaves the property map' => [
            static fn (RenderedElement $element): RenderedElement => $element->withSlots(['sidebar' => []]),
            static fn (RenderedElement $element): array => $element->properties,
        ];
    }

    /**
     * @return iterable<string, array{callable(RenderedElement): RenderedElement}>
     */
    public static function subtreeReusingWithMethodProvider(): iterable
    {
        yield 'withProperty' => [
            static fn (RenderedElement $root): RenderedElement => $root->withProperty('headline', 'Hello'),
        ];
        yield 'withProperties' => [
            static fn (RenderedElement $root): RenderedElement => $root->withProperties(['headline' => 'Hello']),
        ];
    }

    #[TestDox('exposes every constructor argument as a public readonly field')]
    public function testConstructionExposesEveryField(): void
    {
        $child = new RenderedElement('child-1', 'core:text');
        $style = new ElementStyle(['col-span' => 6]);

        $element = new RenderedElement(
            'element-1',
            'core:section',
            ['headline' => 'Hello'],
            ['main' => [$child]],
            $style,
        );

        static::assertSame('element-1', $element->id);
        static::assertSame('core:section', $element->component);
        static::assertSame(['headline' => 'Hello'], $element->properties);
        static::assertSame(['main' => [$child]], $element->slots);
        static::assertSame($style, $element->style);
    }

    #[TestDox('defaults to no properties, no slots and an empty style')]
    public function testConstructionDefaultsToAnEmptyElement(): void
    {
        $element = new RenderedElement('element-1', 'core:text');

        static::assertSame([], $element->properties);
        static::assertSame([], $element->slots);
        static::assertTrue($element->style->isEmpty());
    }

    #[DataProvider('withMethodProvider')]
    #[TestDox('returns a new instance carrying the change when a with method is called')]
    public function testWithMethodReturnsANewInstanceCarryingTheChange(
        callable $mutate,
        callable $read,
        mixed $expected
    ): void {
        $original = new RenderedElement('element-1', 'core:text', ['headline' => 'original']);

        $mutated = $mutate($original);

        static::assertNotSame($original, $mutated);
        static::assertEquals($expected, $read($mutated));
    }

    #[TestDox('accepts a slot holding several rendered elements')]
    public function testConstructorAcceptsASlotWithSeveralChildren(): void
    {
        $first = new RenderedElement('child-1', 'core:text');
        $second = new RenderedElement('child-2', 'core:text');

        $element = new RenderedElement('element-1', 'core:section', [], ['main' => [$first, $second]]);

        static::assertSame([$first, $second], $element->slots['main']);
    }

    #[TestDox('withSlots replaces one slot map with another, dropping the slots it does not name')]
    public function testWithSlotsReplacesTheWholeSlotMap(): void
    {
        $sidebarChild = new RenderedElement('child-2', 'core:text');
        $root = new RenderedElement(
            'element-1',
            'core:section',
            [],
            ['main' => [new RenderedElement('child-1', 'core:text')]]
        );

        $mutated = $root->withSlots(['sidebar' => [$sidebarChild]]);

        static::assertSame(['sidebar' => [$sidebarChild]], $mutated->slots);
    }

    #[TestDox('withProperty keeps the keys it does not name')]
    public function testWithPropertyKeepsTheOtherProperties(): void
    {
        $element = new RenderedElement('element-1', 'core:text', ['headline' => 'Hello', 'tagline' => 'World']);

        $mutated = $element->withProperty('headline', 'Changed');

        static::assertSame(['headline' => 'Changed', 'tagline' => 'World'], $mutated->properties);
    }

    /**
     * @param callable(RenderedElement): RenderedElement $mutate
     * @param callable(RenderedElement): array<array-key, mixed> $readUnnamed
     */
    #[DataProvider('unnamedFieldProvider')]
    #[TestDox('leaves every field it does not name in place')]
    public function testWithMethodLeavesTheUnnamedFieldsInPlace(callable $mutate, callable $readUnnamed): void
    {
        $style = new ElementStyle(['col-span' => 6]);
        $child = new RenderedElement('child-1', 'core:text');
        $element = new RenderedElement('element-1', 'core:section', ['tagline' => 'World'], ['main' => [$child]], $style);

        static::assertCount(1, $element->properties);
        static::assertCount(1, $element->slots);
        static::assertFalse($element->style->isEmpty());

        $unnamedBefore = $readUnnamed($element);
        $mutated = $mutate($element);

        static::assertSame('element-1', $mutated->id);
        static::assertSame('core:section', $mutated->component);
        static::assertSame($style, $mutated->style);
        static::assertSame($unnamedBefore, $readUnnamed($mutated));
    }

    #[TestDox('carries an object property value by identity rather than wrapping or copying it')]
    public function testPropertyValuesRideRaw(): void
    {
        $struct = new StubStruct();

        $element = new RenderedElement('element-1', 'core:image', ['media' => $struct]);

        static::assertSame($struct, $element->properties['media']);
    }

    #[TestDox('withProperty stores an explicit null as a present property rather than dropping the key')]
    public function testWithPropertyStoresAnExplicitNull(): void
    {
        $element = new RenderedElement('element-1', 'core:product-box');

        $mutated = $element->withProperty('product', null);

        static::assertArrayHasKey('product', $mutated->properties);
        static::assertNull($mutated->properties['product']);
    }

    #[DataProvider('permittedPropertyValueProvider')]
    #[TestDox('accepts every permitted property value type at the top level of the property map')]
    public function testConstructorAcceptsAPermittedPropertyValue(mixed $value): void
    {
        $element = new RenderedElement('element-1', 'core:text', ['payload' => $value]);

        static::assertSame($value, $element->properties['payload']);
    }

    #[DataProvider('permittedPropertyValueProvider')]
    #[TestDox('accepts every permitted property value type nested inside an array')]
    public function testConstructorAcceptsAPermittedPropertyValueNestedInsideAnArray(mixed $value): void
    {
        $element = new RenderedElement('element-1', 'core:text', ['payload' => ['rows' => [$value]]]);

        static::assertSame($value, $element->properties['payload']['rows'][0]);
    }

    /**
     * The value walk descends into arrays; the numeric-key ban deliberately does not follow it. A nested
     * array may be a list, whose keys are integers by definition, so folding the two walks together would
     * make every list-valued property throw.
     */
    #[TestDox('accepts a list-valued property, whose integer keys the numeric-key ban does not reach')]
    public function testConstructorAcceptsAListValuedProperty(): void
    {
        $element = new RenderedElement('element-1', 'core:text', ['tags' => ['a', 'b']]);

        static::assertSame(['a', 'b'], $element->properties['tags']);
    }

    /**
     * @param callable(RenderedElement): RenderedElement $mutate
     */
    #[DataProvider('subtreeReusingWithMethodProvider')]
    #[TestDox('reuses its untouched subtree instead of rebuilding it')]
    public function testCopyWithReusesTheUntouchedSubtree(callable $mutate): void
    {
        $grandChild = new RenderedElement('grandchild-1', 'core:text');
        $child = new RenderedElement('child-1', 'core:section', [], ['inner' => [$grandChild]]);
        $root = new RenderedElement('element-1', 'core:section', [], ['main' => [$child]]);

        static::assertSame([$grandChild], $root->slots['main'][0]->slots['inner']);

        $mutated = $mutate($root);

        static::assertSame($child, $mutated->slots['main'][0]);
    }

    /**
     * @param array<array-key, mixed> $slots
     */
    #[DataProvider('malformedSlotMapProvider')]
    #[TestDox('rejects a slot map that does not match the declared shape')]
    public function testConstructorRejectsAMalformedSlotMap(array $slots, ContentSystemException $expected): void
    {
        $this->expectExceptionObject($expected);

        new RenderedElement('element-1', 'core:section', [], $slots);
    }

    /**
     * @param array<array-key, mixed> $properties
     */
    #[DataProvider('numericPropertyKeyProvider')]
    #[TestDox('rejects a numeric property key')]
    public function testConstructorRejectsANumericPropertyKey(array $properties): void
    {
        $this->expectExceptionObject(ContentSystemException::invalidMapKey('Rendered element property map', 'int'));

        new RenderedElement('element-1', 'core:text', $properties);
    }

    #[TestDox('rejects a numeric property name set through withProperty')]
    public function testWithPropertyRejectsANumericPropertyName(): void
    {
        $element = new RenderedElement('element-1', 'core:text');

        $this->expectExceptionObject(ContentSystemException::invalidMapKey('Rendered element property map', 'int'));

        $element->withProperty('12', 'Hello');
    }

    /**
     * The permitted domain names `\BackedEnum`, not `\UnitEnum`. A pure enum case carries no scalar the
     * encoder can serialize, so widening the check to `\UnitEnum` would admit a value that reaches the wire
     * as an empty object. {@see ValueOrigin} is one this module already declares, so the case is a value
     * that really exists here rather than a fixture invented to fail.
     */
    #[TestDox('rejects a pure enum case, which is a UnitEnum but not a BackedEnum')]
    public function testConstructorRejectsAPureEnumPropertyValue(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::unsupportedPropertyValueType('mode', ValueOrigin::class)
        );

        new RenderedElement('element-1', 'core:text', ['mode' => ValueOrigin::DeclaredAuthored]);
    }

    #[TestDox('rejects a property value that is an object outside the permitted domain')]
    public function testConstructorRejectsAnUnsupportedObjectPropertyValue(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::unsupportedPropertyValueType('payload', 'stdClass')
        );

        new RenderedElement('element-1', 'core:text', ['payload' => new \stdClass()]);
    }

    #[TestDox('rejects an unsupported object however deep inside an array it is buried')]
    public function testConstructorRejectsAnUnsupportedObjectNestedInsideAnArray(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::unsupportedPropertyValueType('payload', 'stdClass')
        );

        new RenderedElement('element-1', 'core:text', ['payload' => ['rows' => [['cell' => new \stdClass()]]]]);
    }

    #[TestDox('reports an unsupported property value as a producer defect, not as a client defect')]
    public function testUnsupportedPropertyValueIsAProducerDefect(): void
    {
        try {
            new RenderedElement('element-1', 'core:text', ['payload' => new \stdClass()]);
        } catch (ContentSystemException $exception) {
            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
            static::assertFalse(ContentSystemException::isClientDefect($exception));

            return;
        }

        static::fail('Expected the constructor to reject an unsupported property value');
    }
}
