<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredElement::class)]
class StoredElementTest extends TestCase
{
    #[TestDox('exposes every constructor argument through its read contract')]
    public function testConstructionExposesEveryField(): void
    {
        $child = StoredElementBuilder::create('core:text', 'child-1')->build();
        $requirement = new DataRequirement('product', 'entity', new StubLoaderConfig());
        $definitions = new ContextDefinitions([], []);
        $style = new ElementStyle(['col-span' => 6]);

        $element = new StoredElement(
            'element-1',
            'core:section',
            ['product' => $requirement],
            ['headline' => StoredValue::ofString('Hello')],
            ['main' => [$child]],
            $definitions,
            $style,
            ['product' => 'core:product-detail'],
        );

        static::assertSame('element-1', $element->id);
        static::assertSame('core:section', $element->component);
        static::assertSame(['product' => $requirement], $element->dataRequirements);
        static::assertSame(['main' => [$child]], $element->slots);
        static::assertSame($definitions, $element->contextDefinitions);
        static::assertSame($style, $element->style);
        static::assertSame(['product' => 'core:product-detail'], $element->attributedSpecifications);
        static::assertSame(['headline'], array_keys($element->properties()));
    }

    #[TestDox('serializes a bare element to exactly id, component and properties')]
    public function testJsonSerializeAlwaysEmitsIdComponentAndProperties(): void
    {
        $element = StoredElementBuilder::create('core:text', 'element-1')->build();

        static::assertSame(
            ['id' => 'element-1', 'component' => 'core:text', 'properties' => []],
            $element->jsonSerialize()
        );
    }

    #[DataProvider('withMethodProvider')]
    #[TestDox('returns new instance carrying change and leaves original untouched')]
    public function testWithMethodReturnsANewInstanceAndLeavesTheOriginalUnchanged(
        callable $mutate,
        callable $read,
        mixed $expected
    ): void {
        $original = StoredElementBuilder::create('core:text', 'element-1')
            ->withProperty('headline', 'original')
            ->build();

        $mutated = $mutate($original);

        static::assertNotSame($original, $mutated);
        static::assertEquals($expected, $read($mutated));
        static::assertNotEquals($expected, $read($original));
    }

    #[TestDox('serializes property values unwrapped back to their raw payloads')]
    public function testJsonSerializeUnwrapsPropertyValues(): void
    {
        $element = StoredElementBuilder::create('core:text', 'element-1')
            ->withProperty('headline', 'Hello')
            ->withProperty('tags', ['a', 'b'])
            ->build();

        static::assertSame(
            ['headline' => 'Hello', 'tags' => ['a', 'b']],
            $element->jsonSerialize()['properties']
        );
    }

    #[TestDox('serializes slot children recursively as a JSON array per slot')]
    public function testJsonSerializeRecursesIntoSlotChildren(): void
    {
        $grandChild = StoredElementBuilder::create('core:text', 'grandchild-1')->build();
        $child = StoredElementBuilder::create('core:section', 'child-1')
            ->withSlot('inner', [$grandChild])
            ->build();
        $root = StoredElementBuilder::create('core:section', 'element-1')
            ->withSlot('main', [$child])
            ->build();

        static::assertSame(
            [
                'main' => [
                    [
                        'id' => 'child-1',
                        'component' => 'core:section',
                        'properties' => [],
                        'slots' => [
                            'inner' => [
                                ['id' => 'grandchild-1', 'component' => 'core:text', 'properties' => []],
                            ],
                        ],
                    ],
                ],
            ],
            $root->jsonSerialize()['slots']
        );
    }

    #[DataProvider('omittedWhenEmptyKeyProvider')]
    #[TestDox('emits an optional key only once the element carries something under it')]
    public function testJsonSerializeEmitsAnOptionalKeyOnlyWhenPopulated(
        StoredElement $populated,
        string $key,
        mixed $expected
    ): void {
        $bare = StoredElementBuilder::create('core:text', 'element-1')->build();

        static::assertArrayNotHasKey($key, $bare->jsonSerialize());
        static::assertSame($expected, $populated->jsonSerialize()[$key]);
    }

    #[TestDox('property returns null for a key the element does not carry')]
    public function testPropertyReturnsNullForAnAbsentKey(): void
    {
        $element = StoredElementBuilder::create('core:text', 'element-1')->build();

        static::assertNull($element->property('headline'));
    }

    #[TestDox('property returns a null-variant value for an authored null, distinguishing it from an absent key')]
    public function testPropertyReturnsANullVariantValueForAnAuthoredNull(): void
    {
        $element = StoredElementBuilder::create('core:text', 'element-1')
            ->withProperty('headline', null)
            ->build();

        $value = $element->property('headline');

        static::assertInstanceOf(StoredValue::class, $value);
        static::assertTrue($value->isNull());
    }

    #[TestDox('serializes an empty property map as an array, never as an object')]
    public function testJsonSerializeEmitsAnEmptyPropertyMapAsAnArray(): void
    {
        $element = StoredElementBuilder::create('core:text', 'element-1')->build();

        static::assertSame('{"id":"element-1","component":"core:text","properties":[]}', json_encode($element));
    }

    /**
     * @param callable(string): StoredElement $construct
     */
    #[DataProvider('rejectsNumericMapKeyProvider')]
    #[TestDox('rejects a numeric wiring map key: $_dataName')]
    public function testConstructorRejectsANumericWiringMapKey(
        callable $construct,
        string $mapType,
        string $key
    ): void {
        $this->expectExceptionObject(ContentSystemException::invalidMapKey($mapType, 'int'));

        $construct($key);
    }

    /**
     * The constructor guards its three wiring maps separately, so each map contributes its own cases. PHP casts
     * every numeric array key to an integer, so a numeric string and the bare integer land on the same runtime
     * key; each case label names the form a caller would have written.
     *
     * @return iterable<string, array{callable(string): StoredElement, string, string}>
     */
    public static function rejectsNumericMapKeyProvider(): iterable
    {
        $properties = static fn (string $key): StoredElement => new StoredElement(
            'element-1',
            'core:text',
            properties: [$key => StoredValue::ofString('value')],
        );
        $dataRequirements = static fn (string $key): StoredElement => new StoredElement(
            'element-1',
            'core:text',
            dataRequirements: [$key => new DataRequirement('product', 'entity', new StubLoaderConfig())],
        );
        $slots = static fn (string $key): StoredElement => new StoredElement(
            'element-1',
            'core:section',
            slots: [$key => [StoredElementBuilder::create('core:text', 'child-1')->build()]],
        );

        yield 'property map, zero' => [$properties, 'Element property map', '0'];
        yield 'property map, twelve' => [$properties, 'Element property map', '12'];
        yield 'property map, negative' => [$properties, 'Element property map', '-3'];

        yield 'data requirement map, zero' => [$dataRequirements, 'Element data requirement map', '0'];
        yield 'data requirement map, twelve' => [$dataRequirements, 'Element data requirement map', '12'];
        yield 'data requirement map, negative' => [$dataRequirements, 'Element data requirement map', '-3'];

        yield 'slot map, zero' => [$slots, 'Element slot map', '0'];
        yield 'slot map, twelve' => [$slots, 'Element slot map', '12'];
        yield 'slot map, negative' => [$slots, 'Element slot map', '-3'];
    }

    /**
     * @return iterable<string, array{callable(StoredElement): StoredElement, callable(StoredElement): mixed, mixed}>
     */
    public static function withMethodProvider(): iterable
    {
        yield 'withId' => [
            static fn (StoredElement $element): StoredElement => $element->withId('element-2'),
            static fn (StoredElement $element): string => $element->id,
            'element-2',
        ];
        yield 'withComponent' => [
            static fn (StoredElement $element): StoredElement => $element->withComponent('core:image'),
            static fn (StoredElement $element): string => $element->component,
            'core:image',
        ];
        yield 'withDataRequirements' => [
            static fn (StoredElement $element): StoredElement => $element->withDataRequirements([
                'product' => new DataRequirement('product', 'entity', new StubLoaderConfig()),
            ]),
            static fn (StoredElement $element): array => $element->dataRequirements,
            ['product' => new DataRequirement('product', 'entity', new StubLoaderConfig())],
        ];
        yield 'withProperties' => [
            static fn (StoredElement $element): StoredElement => $element->withProperties([
                'headline' => StoredValue::ofString('changed'),
            ]),
            static fn (StoredElement $element): array => $element->properties(),
            ['headline' => StoredValue::ofString('changed')],
        ];
        yield 'withSlots' => [
            static fn (StoredElement $element): StoredElement => $element->withSlots([
                'main' => [StoredElementBuilder::create('core:text', 'child-1')->build()],
            ]),
            static fn (StoredElement $element): array => $element->slots,
            ['main' => [StoredElementBuilder::create('core:text', 'child-1')->build()]],
        ];
        yield 'withContextDefinitions' => [
            static fn (StoredElement $element): StoredElement => $element->withContextDefinitions(
                new ContextDefinitions(
                    ['product' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
                    []
                )
            ),
            static fn (StoredElement $element): array => $element->contextDefinitions->getAllProviders(),
            ['product' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
        ];
        yield 'withStyle' => [
            static fn (StoredElement $element): StoredElement => $element->withStyle(new ElementStyle(['col-span' => 6])),
            static fn (StoredElement $element): array => $element->style->toArray(),
            ['col-span' => 6],
        ];
        yield 'withAttributedSpecifications' => [
            static fn (StoredElement $element): StoredElement => $element->withAttributedSpecifications([
                'product' => 'core:product-detail',
            ]),
            static fn (StoredElement $element): array => $element->attributedSpecifications,
            ['product' => 'core:product-detail'],
        ];
    }

    /**
     * @return iterable<string, array{StoredElement, string, mixed}>
     */
    public static function omittedWhenEmptyKeyProvider(): iterable
    {
        yield 'dataRequirements' => [
            StoredElementBuilder::create('core:text', 'element-1')
                ->withDataRequirement('product', 'entity', new StubLoaderConfig())
                ->build(),
            'dataRequirements',
            ['product' => ['key' => 'product', 'source' => 'entity', 'config' => []]],
        ];
        yield 'providesContext' => [
            StoredElementBuilder::create('core:text', 'element-1')
                ->withProvider('product', BroadcastDistributionConfig::simple())
                ->build(),
            'providesContext',
            ['product' => ['type' => 'single', 'distribution' => 'broadcast', 'consumerAlias' => null]],
        ];
        yield 'acceptsContext' => [
            StoredElementBuilder::create('core:text', 'element-1')
                ->withConsumer('product', ContextType::Single)
                ->build(),
            'acceptsContext',
            ['product' => ['type' => 'single', 'required' => false]],
        ];
        yield 'style' => [
            StoredElementBuilder::create('core:text', 'element-1')
                ->withStyle(new ElementStyle(['col-span' => 6]))
                ->build(),
            'style',
            ['col-span' => 6],
        ];
        yield 'attributedSpecifications' => [
            StoredElementBuilder::create('core:text', 'element-1')
                ->withAttributedSpecification('product', 'core:product-detail')
                ->build(),
            'attributedSpecifications',
            ['product' => 'core:product-detail'],
        ];
    }
}
