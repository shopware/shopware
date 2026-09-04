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
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Breakpoint;
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
    #[TestDox('resolves the data requirement key to $_dataName')]
    public function testDecodeResolvesTheDataRequirementKey(array $requirement, string $expected): void
    {
        $element = $this->codec()->decode(self::baseWire(['dataRequirements' => ['products' => $requirement]]));

        // The map key always stays the outer one; only the requirement's own key falls back to it.
        static::assertSame(['products'], array_keys($element->dataRequirements));
        static::assertSame($expected, $element->dataRequirements['products']->key);
    }

    /**
     * @param array<string, mixed> $provider
     * @param class-string<DistributionConfig> $expected
     * @param array<string, mixed> $expectedConfig
     */
    #[DataProvider('distributionStrategyProvider')]
    #[TestDox('decode builds the config of $_dataName')]
    public function testDecodeDispatchesEveryDistributionStrategy(array $provider, string $expected, array $expectedConfig): void
    {
        $element = $this->codec()->decode(self::baseWire(['providesContext' => ['product' => $provider]]));

        $config = $element->contextDefinitions->getAllProviders()['product']->distributionConfig;

        static::assertInstanceOf($expected, $config);
        static::assertSame($expectedConfig, $config->toArray());
    }

    #[TestDox('decode accepts an id that only looks numeric')]
    public function testDecodeAcceptsANonCastableNumericLookingId(): void
    {
        $element = $this->codec()->decode(['id' => '012', 'component' => 'core:text', 'properties' => []]);

        static::assertSame('012', $element->id);
    }

    /**
     * @param array<string, mixed> $wire
     */
    #[DataProvider('acceptsNestingAtTheLimitProvider')]
    #[TestDox('accepts $_dataName')]
    public function testDecodeAcceptsNestingAtTheLimit(array $wire): void
    {
        $codec = $this->codec();

        static::assertSame($wire, $codec->encode($codec->decode($wire)));
    }

    #[TestDox('keeps a well-formed style entry the registry no longer knows')]
    public function testDecodeKeepsAnUnknownButWellFormedStyleOption(): void
    {
        $wire = self::baseWire([
            'style' => [
                'flat-option' => 'red',
                'breakpoint-option' => ['md' => 10],
            ],
        ]);

        $codec = $this->codec();

        static::assertSame($wire, $codec->encode($codec->decode($wire)));
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

    #[TestDox('names the element whose data requirement points at an unregistered config serializer source')]
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

    #[TestDox('propagates an unrelated ContentSystemException from a data requirement unchanged')]
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

    /**
     * @param array<array-key, mixed> $style
     */
    #[DataProvider('rejectsMalformedStyleProvider')]
    #[TestDox('decode rejects $_dataName')]
    public function testDecodeRejectsMalformedStyle(array $style, ContentSystemException $expected): void
    {
        $this->expectExceptionObject($expected);

        $this->codec()->decode(self::baseWire(['style' => $style]));
    }

    /**
     * The element-local wiring tier: a consumer map judged against itself, and against the element's own
     * provider map. Each row names the rule's own exception, and each has a sibling in
     * {@see acceptsCleanElementWiringProvider()} one edit away on the tested axis alone.
     *
     * The error code is asserted explicitly: `expectExceptionObject()` compares `getCode()`, which Symfony's
     * HttpException leaves at 0 for every ContentSystemException, so it alone would not tell these three apart.
     *
     * @param array<string, mixed> $wire
     */
    #[DataProvider('rejectsElementLocalWiringProvider')]
    #[TestDox('decode rejects $_dataName')]
    public function testDecodeRejectsAnElementLocalWiringDefect(array $wire, ContentSystemException $expected): void
    {
        try {
            $this->codec()->decode($wire);
            static::fail('Expected decode to reject the element-local wiring defect.');
        } catch (ContentSystemException $exception) {
            static::assertSame($expected->getErrorCode(), $exception->getErrorCode());
            static::assertSame($expected->getMessage(), $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $wire
     * @param list<string> $expectedConsumerKeys
     */
    #[DataProvider('acceptsCleanElementWiringProvider')]
    #[TestDox('decode accepts $_dataName')]
    public function testDecodeAcceptsACleanElementWiringSibling(array $wire, array $expectedConsumerKeys): void
    {
        $element = $this->codec()->decode($wire);

        static::assertSame($expectedConsumerKeys, array_keys($element->contextDefinitions->getAllConsumers()));
    }

    /**
     * The check order decode owes: the per-consumer combination tier finishes across the whole consumer map
     * before the element-local tier starts, and within the element-local tier the rules run in the declared
     * order. Every row below violates two rules at once, so the exception identifies which one decode reached
     * first; the descriptor reports both, which its own test pins.
     *
     * @param array<string, mixed> $wire
     */
    #[DataProvider('pinsElementWiringCheckOrderProvider')]
    #[TestDox('decode throws $_dataName')]
    public function testDecodeThrowsTheFirstRuleInTheDeclaredOrder(array $wire, ContentSystemException $expected): void
    {
        try {
            $this->codec()->decode($wire);
            static::fail('Expected decode to reject the doubly defective element.');
        } catch (ContentSystemException $exception) {
            static::assertSame($expected->getErrorCode(), $exception->getErrorCode());
            static::assertSame($expected->getMessage(), $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, ContentSystemException}>
     */
    public static function rejectsElementLocalWiringProvider(): iterable
    {
        yield 'two consumers landing on one base key' => [
            self::baseWire(['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => true],
                'category' => ['type' => 'single', 'required' => true, 'propertyAlias' => 'product'],
            ]]),
            ContentSystemException::propertyAliasCollision('product', 'product', 'category'),
        ];

        yield 'a redistributing consumer keyed by a dotted path' => [
            self::baseWire(['acceptsContext' => [
                'product.manufacturer' => ['type' => 'single', 'required' => true, 'redistribute' => true],
            ]]),
            ContentSystemException::redistributeWithDottedPath('product.manufacturer'),
        ];

        yield 'a redistributing consumer whose context key an authored provider holds' => [
            self::baseWire([
                'providesContext' => ['product' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => ['product' => ['type' => 'single', 'required' => true, 'redistribute' => true]],
            ]),
            ContentSystemException::redistributeConflict('product'),
        ];

        // The derived provider key is what the consumer writes, so a propertyAlias moves the collision onto
        // the alias: a rule judged on the context key alone would let this one through.
        yield 'a redistributing consumer whose property alias an authored provider holds' => [
            self::baseWire([
                'providesContext' => ['product' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => [
                    'source' => ['type' => 'single', 'required' => true, 'redistribute' => true, 'propertyAlias' => 'product'],
                ],
            ]),
            ContentSystemException::redistributeConflict('source'),
        ];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>}>
     */
    public static function acceptsCleanElementWiringProvider(): iterable
    {
        yield 'two consumers landing on distinct base keys' => [
            self::baseWire(['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => true],
                'category' => ['type' => 'single', 'required' => true, 'propertyAlias' => 'item'],
            ]]),
            ['product', 'category'],
        ];

        yield 'a redistributing consumer keyed by a base key' => [
            self::baseWire(['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => true, 'redistribute' => true],
            ]]),
            ['product'],
        ];

        yield 'a redistributing consumer beside a provider on another key' => [
            self::baseWire([
                'providesContext' => ['other' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => ['product' => ['type' => 'single', 'required' => true, 'redistribute' => true]],
            ]),
            ['product'],
        ];

        yield 'a redistributing consumer whose property alias no provider holds' => [
            self::baseWire([
                'providesContext' => ['product' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => [
                    'source' => ['type' => 'single', 'required' => true, 'redistribute' => true, 'propertyAlias' => 'productList'],
                ],
            ]),
            ['source'],
        ];

        // The planner's documented non-collision case: a consumerAlias renames what children match on, never
        // where the value is read from, so it can equal an authored provider's key without conflicting. The
        // authored provider carries its own alias here, so the two do not meet on the child-facing key either.
        yield 'a consumer alias equal to an authored provider key' => [
            self::baseWire([
                'providesContext' => [
                    'item' => ['type' => 'single', 'distribution' => 'broadcast', 'consumerAlias' => 'other'],
                ],
                'acceptsContext' => [
                    'product' => ['type' => 'single', 'required' => true, 'redistribute' => true, 'consumerAlias' => 'item'],
                ],
            ]),
            ['product'],
        ];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, ContentSystemException}>
     */
    public static function pinsElementWiringCheckOrderProvider(): iterable
    {
        // One consumer violating both tiers at once: it renames without redistributing, and it lands on the
        // base key an earlier consumer already holds.
        yield 'the combination rule for a consumer violating a combination rule and a cross-map rule at once' => [
            self::baseWire(['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => true],
                'category' => [
                    'type' => 'single',
                    'required' => true,
                    'consumerAlias' => 'inner',
                    'propertyAlias' => 'product',
                ],
            ]]),
            ContentSystemException::consumerAliasWithoutRedistribute('category'),
        ];

        // Both violations sit inside the element-local tier, so the declared order within it decides:
        // landing-key uniqueness before the dotted redistribute key.
        yield 'the landing-key rule for an element violating two element-local rules' => [
            self::baseWire(['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => true],
                'product.manufacturer' => ['type' => 'single', 'required' => true, 'redistribute' => true],
            ]]),
            ContentSystemException::propertyAliasCollision('product', 'product', 'product.manufacturer'),
        ];

        // The remaining pair inside the element-local tier, on one consumer: its key is dotted and the key it
        // would derive is one an authored provider holds. Reordering the two checks inside the redistribute
        // loop is what this row catches, and nothing else does.
        yield 'the dotted-key rule for a consumer violating both redistribute rules at once' => [
            self::baseWire([
                'providesContext' => ['product.manufacturer' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => [
                    'product.manufacturer' => ['type' => 'single', 'required' => true, 'redistribute' => true],
                ],
            ]),
            ContentSystemException::redistributeWithDottedPath('product.manufacturer'),
        ];

        // The two element-local rules on disjoint consumers, which is what pins the order of the two loops
        // rather than the order of the checks inside one of them.
        yield 'the landing-key rule over a provider conflict on a different consumer' => [
            self::baseWire([
                'providesContext' => ['shared' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => [
                    'product' => ['type' => 'single', 'required' => true],
                    'category' => ['type' => 'single', 'required' => true, 'propertyAlias' => 'product'],
                    'shared' => ['type' => 'single', 'required' => true, 'redistribute' => true],
                ],
            ]),
            ContentSystemException::propertyAliasCollision('product', 'product', 'category'),
        ];

        // Tier-major, not consumer-major: the earlier consumer's element-local violation waits for the whole
        // combination tier, so the later consumer's combination violation is what decode reports.
        yield 'the combination rule for a later consumer over an earlier cross-map violation' => [
            self::baseWire([
                'providesContext' => ['early' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => [
                    'early' => ['type' => 'single', 'required' => true, 'redistribute' => true],
                    'late' => ['type' => 'single', 'required' => true, 'propertyAlias' => 'nested.key'],
                ],
            ]),
            ContentSystemException::propertyAliasWithDotNotation('late', 'nested.key'),
        ];
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
     * @return iterable<string, array{array<string, mixed>, class-string<DistributionConfig>, array<string, mixed>}>
     */
    public static function distributionStrategyProvider(): iterable
    {
        yield 'a broadcast provider' => [
            ['type' => 'collection', 'distribution' => 'broadcast'],
            BroadcastDistributionConfig::class,
            ['distribution' => 'broadcast', 'consumerAlias' => null],
        ];

        yield 'an indexed provider' => [
            ['type' => 'collection', 'distribution' => 'indexed'],
            IndexedDistributionConfig::class,
            ['distribution' => 'indexed', 'consumerAlias' => null],
        ];

        yield 'an iterator provider' => [
            ['type' => 'collection', 'distribution' => 'iterator'],
            IteratorDistributionConfig::class,
            ['distribution' => 'iterator', 'consumerAlias' => null],
        ];

        yield 'a keyed provider' => [
            ['type' => 'single', 'distribution' => 'keyed', 'keyProperty' => 'sku'],
            KeyedDistributionConfig::class,
            ['distribution' => 'keyed', 'keyProperty' => 'sku', 'consumerAlias' => null],
        ];

        yield 'a sliced provider' => [
            ['type' => 'collection', 'distribution' => 'sliced', 'sliceSize' => 4],
            SlicedDistributionConfig::class,
            ['distribution' => 'sliced', 'sliceSize' => 4, 'consumerAlias' => null],
        ];
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
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function acceptsNestingAtTheLimitProvider(): iterable
    {
        yield 'an element chain exactly at the nesting limit' => [self::nestedElements(51)];
        yield 'a property payload exactly at the nesting limit' => [self::elementWithNestedValue(51)];
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
