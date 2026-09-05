<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Codec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementWiringDecoder;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IndexedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IteratorDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\SlicedDistributionConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * The element-local wiring tier of decode, and the routing of a decoded provider to its declared distribution
 * strategy's config. Every test here reaches the rules through `StoredElementCodec::decode()`, which is the
 * only entry point the composing codec exposes.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredElementWiringDecoder::class)]
class StoredElementWiringDecoderTest extends StoredElementCodecTestCase
{
    /**
     * @param array<string, mixed> $provider
     * @param class-string<DistributionConfig> $expected
     * @param array<string, mixed> $expectedConfig
     */
    #[DataProvider('distributionStrategyProvider')]
    #[TestDox('builds the config of $_dataName')]
    public function testDecodeDispatchesEveryDistributionStrategy(array $provider, string $expected, array $expectedConfig): void
    {
        $element = $this->codec()->decode(self::baseWire(['providesContext' => ['product' => $provider]]));

        $config = $element->contextDefinitions->getAllProviders()['product']->distributionConfig;

        static::assertInstanceOf($expected, $config);
        static::assertSame($expectedConfig, $config->toArray());
    }

    /**
     * The two fields the element-local rules read are asserted alongside the key list: `rejectInvalidElementWiring`
     * opens its redistribute loop with a `!$consumer->redistribute` early exit, so a row whose consumer decoded
     * `redistribute` as false would clear every rule it names without the rules ever running, and a key-list-only
     * assertion cannot tell that apart from a clean pass.
     *
     * @param array<string, mixed> $wire
     * @param array<string, array{bool, string|null}> $expectedConsumers
     */
    #[DataProvider('acceptsCleanElementWiringProvider')]
    #[TestDox('accepts $_dataName')]
    public function testDecodeAcceptsACleanElementWiringSibling(array $wire, array $expectedConsumers): void
    {
        $element = $this->codec()->decode($wire);

        $decoded = array_map(
            static fn (ContextConsumer $consumer): array => [$consumer->redistribute, $consumer->propertyAlias],
            $element->contextDefinitions->getAllConsumers()
        );

        static::assertSame($expectedConsumers, $decoded);
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
    #[TestDox('rejects $_dataName')]
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
     * The check order decode owes: the per-consumer combination tier finishes across the whole consumer map
     * before the element-local tier starts, and within the element-local tier the rules run in the declared
     * order. Every row below violates two rules at once, so the exception identifies which one decode reached
     * first; the descriptor reports both, which its own test pins.
     *
     * @param array<string, mixed> $wire
     */
    #[DataProvider('pinsElementWiringCheckOrderProvider')]
    #[TestDox('throws $_dataName')]
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
     * A consumer's scope, which decides whether it matches an ancestor's provided context or the layout's
     * root-ambient context. The absent case is the one that carries a rule rather than a value: an authored
     * consumer that never mentions `scope` is parent-scoped, and a decode that returned anything else would
     * re-point every consumer stored before the key existed.
     *
     * @param array<string, mixed> $consumer
     * @param array<string, mixed> $expectedWire
     */
    #[DataProvider('decodesConsumerScopeProvider')]
    #[TestDox('decodes $_dataName')]
    public function testDecodeReadsTheConsumerScope(array $consumer, ConsumerScope $expected, array $expectedWire): void
    {
        $element = $this->codec()->decode(self::baseWire(['acceptsContext' => ['product' => $consumer]]));

        static::assertSame($expected, $element->contextDefinitions->getAllConsumers()['product']->scope);
    }

    /**
     * What a decoded consumer writes back for its scope. The parent case carries the rule: it is the absent
     * key's meaning, so writing it would change the stored shape of every consumer authored before the key
     * existed without changing what any of them do. Asserted here rather than against
     * {@see ContextConsumer} directly, which `phpunit.xml.dist` excludes from the coverage source.
     *
     * @param array<string, mixed> $consumer
     * @param array<string, mixed> $expectedWire
     */
    #[DataProvider('decodesConsumerScopeProvider')]
    #[TestDox('writes back $_dataName')]
    public function testDecodedConsumerEncodesItsScopeOnlyWhenRoot(array $consumer, ConsumerScope $expected, array $expectedWire): void
    {
        $element = $this->codec()->decode(self::baseWire(['acceptsContext' => ['product' => $consumer]]));

        static::assertSame($expectedWire, $element->contextDefinitions->getAllConsumers()['product']->jsonSerialize());
    }

    /**
     * A root-scoped consumer takes root-ambient context directly, so it has nothing to hand on and cannot
     * declare `redistribute`. The rule sits in the per-consumer combination tier, beside the two alias rules.
     */
    #[TestDox('rejects a root-scoped consumer that also redistributes')]
    public function testDecodeRejectsARootScopedRedistributingConsumer(): void
    {
        $expected = ContentSystemException::rootScopeWithRedistribute('product');

        try {
            $this->codec()->decode(self::baseWire(['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => true, 'redistribute' => true, 'scope' => 'root'],
            ]]));
            static::fail('Expected decode to reject the root-scoped redistributing consumer.');
        } catch (ContentSystemException $exception) {
            static::assertSame($expected->getErrorCode(), $exception->getErrorCode());
            static::assertSame($expected->getMessage(), $exception->getMessage());
        }
    }

    /**
     * The structural strictness tier this class owns independently of the composing codec: a malformed wiring
     * container, key or field fails decode outright. {@see StoredElementCodecStructuralDecodeTest} exercises
     * the same rules through `decode()`'s public surface for the composing class's own coverage; the rows here
     * pin them directly against this class.
     *
     * @param array<array-key, mixed> $wire
     */
    #[DataProvider('rejectsStructurallyMalformedWiringProvider')]
    #[TestDox('rejects $_dataName')]
    public function testDecodeRejectsAStructurallyMalformedWiringEntry(array $wire, ContentSystemException $expected): void
    {
        $this->expectExceptionObject($expected);

        $this->codec()->decode($wire);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, ContentSystemException}>
     */
    public static function rejectsElementLocalWiringProvider(): iterable
    {
        yield 'two consumers sharing one base key' => [
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
     * Each row's expectation maps every decoded consumer key to its `[redistribute, propertyAlias]` pair, the two
     * fields the element-local rules judge.
     *
     * @return iterable<string, array{array<string, mixed>, array<string, array{bool, string|null}>}>
     */
    public static function acceptsCleanElementWiringProvider(): iterable
    {
        yield 'two consumers writing distinct base keys' => [
            self::baseWire(['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => true],
                'category' => ['type' => 'single', 'required' => true, 'propertyAlias' => 'item'],
            ]]),
            ['product' => [false, null], 'category' => [false, 'item']],
        ];

        yield 'a redistributing consumer keyed by a base key' => [
            self::baseWire(['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => true, 'redistribute' => true],
            ]]),
            ['product' => [true, null]],
        ];

        yield 'a redistributing consumer beside a provider on another key' => [
            self::baseWire([
                'providesContext' => ['other' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => ['product' => ['type' => 'single', 'required' => true, 'redistribute' => true]],
            ]),
            ['product' => [true, null]],
        ];

        yield 'a redistributing consumer whose property alias no provider holds' => [
            self::baseWire([
                'providesContext' => ['product' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => [
                    'source' => ['type' => 'single', 'required' => true, 'redistribute' => true, 'propertyAlias' => 'productList'],
                ],
            ]),
            ['source' => [true, 'productList']],
        ];
        // A consumerAlias equal to an authored provider key is deliberately absent: rejectInvalidElementWiring
        // reads propertyAlias and redistribute only, so such a row takes the same path as the one above it, and
        // the consumerAlias-with-redistribute branch is already covered by roundTripProvider's every-field row.
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
        // base-key uniqueness before the dotted redistribute key.
        yield 'the base-key rule for an element violating two element-local rules' => [
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
        yield 'the base-key rule over a provider conflict on a different consumer' => [
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

        // The scope rule sits at the end of the combination tier, so a consumer that is both root-scoped while
        // redistributing and keyed by a dotted path is reported by the combination tier, not by the
        // element-local dotted-redistribute rule that would otherwise claim it.
        yield 'the root-scope rule for a root-scoped consumer redistributing under a dotted key' => [
            self::baseWire(['acceptsContext' => [
                'product.manufacturer' => [
                    'type' => 'single',
                    'required' => true,
                    'redistribute' => true,
                    'scope' => 'root',
                ],
            ]]),
            ContentSystemException::rootScopeWithRedistribute('product.manufacturer'),
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
     * @return iterable<string, array{array<string, mixed>, ConsumerScope, array<string, mixed>}>
     */
    public static function decodesConsumerScopeProvider(): iterable
    {
        yield 'a consumer declaring no scope as parent-scoped' => [
            ['type' => 'single', 'required' => true],
            ConsumerScope::Parent,
            ['type' => 'single', 'required' => true],
        ];

        yield 'a consumer declaring the parent scope explicitly' => [
            ['type' => 'single', 'required' => true, 'scope' => 'parent'],
            ConsumerScope::Parent,
            ['type' => 'single', 'required' => true],
        ];

        yield 'a root-scoped consumer' => [
            ['type' => 'single', 'required' => true, 'scope' => 'root'],
            ConsumerScope::Root,
            ['type' => 'single', 'required' => true, 'scope' => 'root'],
        ];

        // The scope key is written last, after the two aliases, which is what a round trip through the
        // composing codec compares against the payload it was given.
        yield 'a root-scoped consumer carrying a property alias' => [
            ['type' => 'single', 'required' => true, 'propertyAlias' => 'entry', 'scope' => 'root'],
            ConsumerScope::Root,
            ['type' => 'single', 'required' => true, 'propertyAlias' => 'entry', 'scope' => 'root'],
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
     * @return iterable<string, array{array<string, mixed>, ContentSystemException}>
     */
    public static function rejectsStructurallyMalformedWiringProvider(): iterable
    {
        yield 'a non-array providesContext' => [
            self::baseWire(['providesContext' => 'not-an-array']),
            ContentSystemException::invalidFieldValueType('providesContext', 'array', 'string'),
        ];

        yield 'a non-array acceptsContext' => [
            self::baseWire(['acceptsContext' => 'not-an-array']),
            ContentSystemException::invalidFieldValueType('acceptsContext', 'array', 'string'),
        ];

        yield 'a non-string key inside providesContext' => [
            self::baseWire([
                'providesContext' => [0 => ['type' => 'single', 'distribution' => 'keyed', 'keyProperty' => 'sku']],
            ]),
            ContentSystemException::invalidMapKey('Element context provider map', 'int'),
        ];

        yield 'a non-string key inside acceptsContext' => [
            self::baseWire([
                'acceptsContext' => [0 => ['type' => 'single', 'required' => true]],
            ]),
            ContentSystemException::invalidMapKey('Element context consumer map', 'int'),
        ];

        yield 'a non-array provider config' => [
            self::baseWire(['providesContext' => ['product' => 'not-an-array']]),
            ContentSystemException::invalidFieldValueType('providesContext[product]', 'array', 'string'),
        ];

        yield 'a non-array consumer config' => [
            self::baseWire(['acceptsContext' => ['items' => 'not-an-array']]),
            ContentSystemException::invalidFieldValueType('acceptsContext[items]', 'array', 'string'),
        ];

        yield 'an unparseable provider context-type' => [
            self::baseWire([
                'providesContext' => ['product' => ['type' => 'bogus-type', 'distribution' => 'keyed', 'keyProperty' => 'sku']],
            ]),
            ContentSystemException::invalidFieldValueType('providesContext[product].type', implode('|', ContextType::values()), 'string'),
        ];

        yield 'an unparseable provider distribution strategy' => [
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

        yield 'a consumer entry with a non-bool redistribute' => [
            self::baseWire(['acceptsContext' => ['items' => ['type' => 'single', 'required' => true, 'redistribute' => 'yes']]]),
            ContentSystemException::invalidFieldValueType('acceptsContext[items].redistribute', 'bool', 'string'),
        ];

        yield 'a consumer entry with a non-string consumerAlias' => [
            self::baseWire(['acceptsContext' => ['items' => ['type' => 'single', 'required' => true, 'consumerAlias' => 42]]]),
            ContentSystemException::invalidFieldValueType('acceptsContext[items].consumerAlias', 'string', 'int'),
        ];

        yield 'a consumer entry with a non-string propertyAlias' => [
            self::baseWire(['acceptsContext' => ['items' => ['type' => 'single', 'required' => true, 'propertyAlias' => 42]]]),
            ContentSystemException::invalidFieldValueType('acceptsContext[items].propertyAlias', 'string', 'int'),
        ];

        // A present `scope` must name a case. The null row is what separates absent from present-null: the
        // absent key is the parent default, while a written null is a payload the client got wrong.
        yield 'a consumer entry with a scope outside the enum' => [
            self::baseWire(['acceptsContext' => ['items' => ['type' => 'single', 'required' => true, 'scope' => 'ancestor']]]),
            ContentSystemException::invalidFieldValueType('acceptsContext[items].scope', implode('|', ConsumerScope::values()), 'string'),
        ];

        yield 'a consumer entry with a null scope' => [
            self::baseWire(['acceptsContext' => ['items' => ['type' => 'single', 'required' => true, 'scope' => null]]]),
            ContentSystemException::invalidFieldValueType('acceptsContext[items].scope', implode('|', ConsumerScope::values()), 'null'),
        ];

        yield 'a consumer entry with a non-string scope' => [
            self::baseWire(['acceptsContext' => ['items' => ['type' => 'single', 'required' => true, 'scope' => 42]]]),
            ContentSystemException::invalidFieldValueType('acceptsContext[items].scope', implode('|', ConsumerScope::values()), 'int'),
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

        yield 'a non-string key inside a provider config' => [
            self::baseWire([
                'providesContext' => [
                    'product' => (array) json_decode(
                        '{"type":"single","distribution":"broadcast","12":"x"}',
                        true,
                        512,
                        \JSON_THROW_ON_ERROR
                    ),
                ],
            ]),
            ContentSystemException::invalidMapKey('providesContext[product]', 'int'),
        ];
    }
}
