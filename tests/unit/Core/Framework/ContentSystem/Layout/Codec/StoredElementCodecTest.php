<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Codec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfigSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IndexedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IteratorDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\SlicedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredElementCodec::class)]
class StoredElementCodecTest extends TestCase
{
    /**
     * @param array<string, mixed> $wire
     */
    #[DataProvider('roundTripProvider')]
    #[TestDox('decode then encode returns $_dataName unchanged')]
    public function testRoundTrip(array $wire): void
    {
        $codec = $this->codec();

        static::assertSame($wire, $codec->encode($codec->decode($wire)));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function roundTripProvider(): iterable
    {
        yield 'a minimal element' => [[
            'id' => 'el-1',
            'component' => 'core:text',
            'properties' => [],
        ]];

        yield 'an element carrying every field' => [[
            'id' => 'el-1',
            'component' => 'core:section',
            'properties' => [
                'title' => 'Hello',
                'count' => 3,
                'ratio' => 1.5,
                'flag' => true,
                'nothing' => null,
                'tags' => ['a', 'b'],
                'meta' => ['unit' => 'px'],
            ],
            'dataRequirements' => [
                'products' => [
                    'key' => 'products',
                    'source' => 'entity',
                    'config' => ['entity' => 'product', 'property' => 'productId'],
                ],
            ],
            'slots' => [
                'main' => [
                    ['id' => 'child-1', 'component' => 'core:text', 'properties' => []],
                ],
            ],
            'providesContext' => [
                'product' => [
                    'type' => 'single',
                    'distribution' => 'keyed',
                    'keyProperty' => 'sku',
                    'consumerAlias' => null,
                ],
            ],
            'acceptsContext' => [
                'items' => [
                    'type' => 'collection',
                    'required' => true,
                    'redistribute' => true,
                    'consumerAlias' => 'inner',
                    'propertyAlias' => 'entries',
                ],
            ],
            'style' => ['col-span' => ['md' => 6]],
            'attributedSpecifications' => ['image' => 'core:product-image'],
        ]];
    }

    #[TestDox('encode omits every empty optional key, leaving the three always-present ones')]
    public function testEncodeOmitsEmptyOptionalKeys(): void
    {
        $wire = [
            'id' => 'el-1',
            'component' => 'core:text',
            'properties' => ['title' => 'Hello'],
            'dataRequirements' => [],
            'slots' => [],
            'providesContext' => [],
            'acceptsContext' => [],
            'style' => [],
            'attributedSpecifications' => [],
        ];

        $codec = $this->codec();

        static::assertSame(
            ['id' => 'el-1', 'component' => 'core:text', 'properties' => ['title' => 'Hello']],
            $codec->encode($codec->decode($wire))
        );
    }

    #[TestDox('encode produces the canonical storage shape of an element it did not decode')]
    public function testEncodeProducesTheCanonicalShape(): void
    {
        $element = StoredElementBuilder::create('core:text', 'el-1')
            ->withProperty('title', 'Hello')
            ->withStyle(new ElementStyle(['col-span' => ['md' => 6]]))
            ->build();

        static::assertSame(
            [
                'id' => 'el-1',
                'component' => 'core:text',
                'properties' => ['title' => 'Hello'],
                'style' => ['col-span' => ['md' => 6]],
            ],
            $this->codec()->encode($element)
        );
    }

    /**
     * @param array<string, mixed> $requirement
     */
    #[DataProvider('dataRequirementKeyProvider')]
    #[TestDox('decode takes $_dataName')]
    public function testDecodeResolvesTheDataRequirementKey(array $requirement, string $expected): void
    {
        $element = $this->codec()->decode(self::baseWire(['dataRequirements' => ['products' => $requirement]]));

        // The map key always stays the outer one; only the requirement's own key falls back to it.
        static::assertSame(['products'], array_keys($element->dataRequirements));
        static::assertSame($expected, $element->dataRequirements['products']->key);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function dataRequirementKeyProvider(): iterable
    {
        $config = ['entity' => 'product', 'property' => 'productId'];

        yield 'the map key when the entry carries no inner key' => [
            ['source' => 'entity', 'config' => $config],
            'products',
        ];

        yield 'the map key when the inner key is an explicit null' => [
            ['key' => null, 'source' => 'entity', 'config' => $config],
            'products',
        ];

        yield 'the inner key when the entry carries both' => [
            ['key' => 'featured', 'source' => 'entity', 'config' => $config],
            'featured',
        ];
    }

    /**
     * @param array<string, mixed> $provider
     * @param class-string<DistributionConfig> $expected
     */
    #[DataProvider('distributionStrategyProvider')]
    #[TestDox('decode builds the config of $_dataName')]
    public function testDecodeDispatchesEveryDistributionStrategy(array $provider, string $expected): void
    {
        $element = $this->codec()->decode(self::baseWire(['providesContext' => ['product' => $provider]]));

        static::assertInstanceOf($expected, $element->contextDefinitions->getAllProviders()['product']->distributionConfig);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, class-string<DistributionConfig>}>
     */
    public static function distributionStrategyProvider(): iterable
    {
        yield 'a broadcast provider' => [
            ['type' => 'collection', 'distribution' => 'broadcast'],
            BroadcastDistributionConfig::class,
        ];

        yield 'an indexed provider' => [
            ['type' => 'collection', 'distribution' => 'indexed'],
            IndexedDistributionConfig::class,
        ];

        yield 'an iterator provider' => [
            ['type' => 'collection', 'distribution' => 'iterator'],
            IteratorDistributionConfig::class,
        ];

        yield 'a keyed provider' => [
            ['type' => 'single', 'distribution' => 'keyed', 'keyProperty' => 'sku'],
            KeyedDistributionConfig::class,
        ];

        yield 'a sliced provider' => [
            ['type' => 'collection', 'distribution' => 'sliced', 'sliceSize' => 4],
            SlicedDistributionConfig::class,
        ];
    }

    #[TestDox('decode rejects a top-level key the element wire shape does not carry')]
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
    #[TestDox('decode rejects $_dataName as an element id')]
    public function testDecodeRejectsIdsOutsideTheValueDomain(string $id, ContentSystemException $expected): void
    {
        $this->expectExceptionObject($expected);

        $this->codec()->decode(['id' => $id, 'component' => 'core:text', 'properties' => []]);
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

    #[TestDox('decode accepts an id that only looks numeric')]
    public function testDecodeAcceptsANonCastableNumericLookingId(): void
    {
        $element = $this->codec()->decode(['id' => '012', 'component' => 'core:text', 'properties' => []]);

        static::assertSame('012', $element->id);
    }

    /**
     * @param array<array-key, mixed> $wire
     */
    #[DataProvider('numericWiringKeyProvider')]
    #[TestDox('decode rejects $_dataName')]
    public function testDecodeRejectsNumericWiringKeys(array $wire, ContentSystemException $expected): void
    {
        $this->expectExceptionObject($expected);

        $this->codec()->decode($wire);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, ContentSystemException}>
     */
    public static function numericWiringKeyProvider(): iterable
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
     * @param array<string, mixed> $wire
     */
    #[DataProvider('depthRejectionProvider')]
    #[TestDox('decode rejects $_dataName')]
    public function testDecodeRejectsNestingPastTheLimit(array $wire, ContentSystemException $expected): void
    {
        $this->expectExceptionObject($expected);

        $this->codec()->decode($wire);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, ContentSystemException}>
     */
    public static function depthRejectionProvider(): iterable
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
     * @param array<string, mixed> $wire
     */
    #[DataProvider('depthAcceptanceProvider')]
    #[TestDox('decode accepts $_dataName')]
    public function testDecodeAcceptsNestingAtTheLimit(array $wire): void
    {
        $codec = $this->codec();

        static::assertSame($wire, $codec->encode($codec->decode($wire)));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function depthAcceptanceProvider(): iterable
    {
        yield 'an element chain exactly at the nesting limit' => [self::nestedElements(51)];
        yield 'a property payload exactly at the nesting limit' => [self::elementWithNestedValue(51)];
    }

    /**
     * @param array<string, mixed> $wire
     */
    #[DataProvider('structuralDefectProvider')]
    #[TestDox('decode throws for $_dataName')]
    public function testDecodeThrowsForAStructuralDefect(array $wire, ContentSystemException $expected): void
    {
        $this->expectExceptionObject($expected);

        $this->codec()->decode($wire);
    }

    /**
     * Pins every deliberate strictness divergence: a structural container that the replaced code tolerated
     * (or silently defaulted) now fails decode instead of admitting a shape the codec cannot represent.
     *
     * @return iterable<string, array{array<string, mixed>, ContentSystemException}>
     */
    public static function structuralDefectProvider(): iterable
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

    #[TestDox('decode names the element whose data requirement points at an unregistered config serializer source')]
    public function testDecodeThrowsWithElementIdWhenSourceUnregistered(): void
    {
        $provider = new DataLoaderConfigSerializerProvider(new ServiceLocator([]));
        $codec = new StoredElementCodec($provider);

        $wire = self::baseWire([
            'id' => 'el-unregistered',
            'dataRequirements' => [
                'products' => ['source' => 'removed_plugin_source', 'config' => []],
            ],
        ]);

        $expected = ContentSystemException::configSerializerNotRegistered('removed_plugin_source', 'el-unregistered');

        $this->expectExceptionObject($expected);

        $codec->decode($wire);
    }

    #[TestDox('decode propagates an unrelated ContentSystemException from a data requirement unchanged')]
    public function testDecodePropagatesUnrelatedContentSystemExceptionFromDataRequirements(): void
    {
        $internalFault = ContentSystemException::invalidFieldType(AbstractContentDataLoaderConfig::class, 'string');

        $failingSerializer = static::createStub(AbstractContentDataLoaderConfigSerializer::class);
        $failingSerializer->method('decode')->willThrowException($internalFault);

        $provider = new DataLoaderConfigSerializerProvider(new ServiceLocator(['broken_source' => static fn () => $failingSerializer]));
        $codec = new StoredElementCodec($provider);

        $wire = self::baseWire([
            'dataRequirements' => [
                'products' => ['source' => 'broken_source', 'config' => []],
            ],
        ]);

        $this->expectExceptionObject($internalFault);

        $codec->decode($wire);
    }

    #[TestDox('decode drops a structurally invalid style option entry rather than throwing')]
    public function testDecodeDropsAnInvalidStyleEntryInsteadOfThrowing(): void
    {
        $wire = self::baseWire([
            'style' => [
                0 => ['bad' => 1],
                'flat-option' => 'red',
                'breakpoint-option' => ['bogus-breakpoint' => 5, 'md' => 10],
                'all-invalid-breakpoints' => ['bogus-breakpoint' => 5],
                'null-option' => null,
            ],
        ]);

        $codec = $this->codec();

        static::assertSame(
            array_merge($wire, ['style' => ['flat-option' => 'red', 'breakpoint-option' => ['md' => 10]]]),
            $codec->encode($codec->decode($wire))
        );
    }

    /**
     * A chain of `$count` elements, each the single child of the one above it, so the deepest sits `$count - 1`
     * levels below the root.
     *
     * @return array<string, mixed>
     */
    private static function nestedElements(int $count): array
    {
        $element = ['id' => 'el-' . ($count - 1), 'component' => 'core:text', 'properties' => []];

        for ($level = $count - 2; $level >= 0; --$level) {
            $element = [
                'id' => 'el-' . $level,
                'component' => 'core:section',
                'properties' => [],
                'slots' => ['main' => [$element]],
            ];
        }

        return $element;
    }

    /**
     * One element whose single property holds `$count` nested single-entry lists, so the innermost list sits
     * `$count - 1` levels below the property payload.
     *
     * @return array<string, mixed>
     */
    private static function elementWithNestedValue(int $count): array
    {
        $value = 'leaf';

        for ($level = 0; $level < $count; ++$level) {
            $value = [$value];
        }

        return ['id' => 'el-1', 'component' => 'core:text', 'properties' => ['deep' => $value]];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function baseWire(array $overrides): array
    {
        return array_merge([
            'id' => 'el-1',
            'component' => 'core:text',
            'properties' => [],
        ], $overrides);
    }

    /**
     * The provider is stubbed down to routing; the `entity` source's real config serializer does the decoding,
     * so every `config` in this file's fixtures has to be a shape production could actually store.
     */
    private function codec(): StoredElementCodec
    {
        $serializer = new EntityLoaderConfigSerializer();

        $configProvider = static::createStub(DataLoaderConfigSerializerProvider::class);
        $configProvider->method('decode')->willReturnCallback(
            /**
             * @param array<string, mixed> $data
             */
            static fn (string $source, array $data): AbstractContentDataLoaderConfig => $serializer->decode($data)
        );

        return new StoredElementCodec($configProvider);
    }
}
