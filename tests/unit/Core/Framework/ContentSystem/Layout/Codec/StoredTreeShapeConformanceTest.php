<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Codec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\PropertyTypeConformanceValidator;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeConstraints;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionConstraintDeriver;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The write-path descriptor and the read-path codec are two independent expressions of one stored-forest
 * shape, so they drift: a payload the descriptor admits but the codec cannot decode is persisted once and
 * then throws on every later read. One table of payloads, each with the verdict both sides owe it, pins the
 * agreement itself rather than the instances of it found so far.
 *
 * A row is {@see ACCEPTED} when the descriptor reports no violation and decode succeeds, {@see REJECTED} when
 * the descriptor reports at least one violation and decode throws, and {@see DESCRIPTOR_ONLY} for a deliberate
 * divergence: the write boundary knows something decode does not and rejects a payload decode still reads.
 * That direction is safe — it turns away a write instead of stranding a stored row — and every such row names
 * why it diverges. The unsafe direction has no verdict, because no payload is allowed to have it.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredTreeConstraints::class)]
#[CoversClass(StoredElementCodec::class)]
#[CoversClass(StoredTreeCodec::class)]
class StoredTreeShapeConformanceTest extends TestCase
{
    private const ACCEPTED = 'accepted by both sides';

    private const REJECTED = 'rejected by both sides';

    private const DESCRIPTOR_ONLY = 'rejected on write, still readable';

    /**
     * @param array<array-key, mixed> $forest
     */
    #[DataProvider('acceptedPayloadProvider')]
    #[TestDox('both sides accept $_dataName')]
    public function testBothSidesAcceptAConformingPayload(array $forest): void
    {
        static::assertSame(
            [],
            $this->descriptorViolations($forest),
            'The write-path descriptor reported a violation for a payload the read-path codec decodes.'
        );

        static::assertNull(
            $this->codecRejection($forest),
            'The read-path codec rejected a payload the write-path descriptor accepts, so such a payload '
            . 'would be persisted and then fail every read.'
        );
    }

    /**
     * @param array<array-key, mixed> $forest
     */
    #[DataProvider('rejectedPayloadProvider')]
    #[TestDox('both sides reject $_dataName')]
    public function testBothSidesRejectANonConformingPayload(array $forest): void
    {
        $codecRejection = $this->codecRejection($forest);

        static::assertNotNull(
            $codecRejection,
            'The read-path codec decoded a payload the write-path descriptor rejects.'
        );

        static::assertNotSame(
            [],
            $this->descriptorViolations($forest),
            'The write-path descriptor accepted a payload the read-path codec rejects with: ' . $codecRejection
        );
    }

    /**
     * @param array<array-key, mixed> $forest
     */
    #[DataProvider('descriptorOnlyPayloadProvider')]
    #[TestDox('the descriptor alone rejects $_dataName')]
    public function testOnlyTheDescriptorRejectsADeliberateDivergence(array $forest, string $reason): void
    {
        static::assertNotSame(
            [],
            $this->descriptorViolations($forest),
            'The write-path descriptor no longer rejects a payload it deliberately rejects: ' . $reason
        );

        static::assertNull(
            $this->codecRejection($forest),
            'The read-path codec now rejects a payload it deliberately reads: ' . $reason
        );
    }

    /**
     * A null forest cannot reach {@see StoredTreeCodec::decode()} at all — its parameter is typed `array`, so
     * passing null fails as a PHP type error, not a caught {@see ContentSystemException}. That failure *shape*
     * does not fit the shared {@see shapeTable()}'s REJECTED verdict, whose codec side asserts a caught
     * `ContentSystemException`, and the table's own data providers type their `$forest` parameter as `array`,
     * so a `null` row cannot be carried through them either. This asserts the descriptor half directly instead.
     */
    #[TestDox('the descriptor alone rejects a null forest')]
    public function testDescriptorRejectsANullForest(): void
    {
        $validator = $this->validator();

        $violations = $validator->validate(null, $this->constraints()->build());

        static::assertGreaterThanOrEqual(
            1,
            $violations->count(),
            'The write-path descriptor no longer rejects a null forest.'
        );
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function acceptedPayloadProvider(): iterable
    {
        foreach (self::shapeTable() as $name => [$forest, $verdict]) {
            if ($verdict === self::ACCEPTED) {
                yield $name => [$forest];
            }
        }
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function rejectedPayloadProvider(): iterable
    {
        foreach (self::shapeTable() as $name => [$forest, $verdict]) {
            if ($verdict === self::REJECTED) {
                yield $name => [$forest];
            }
        }
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function descriptorOnlyPayloadProvider(): iterable
    {
        foreach (self::shapeTable() as $name => [$forest, $verdict, $reason]) {
            if ($verdict === self::DESCRIPTOR_ONLY) {
                yield $name => [$forest, $reason];
            }
        }
    }

    /**
     * The one table. Each row is a stored forest, the verdict both sides owe it, and — for a deliberate
     * divergence only — why the two sides are allowed to differ on it.
     *
     * @return iterable<string, array{array<array-key, mixed>, string, string}>
     */
    public static function shapeTable(): iterable
    {
        yield 'a forest with no roots' => [[], self::ACCEPTED, ''];

        yield 'an element carrying every field' => [
            [[
                'id' => 'root-1',
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
                    'main' => [['id' => 'child-1', 'component' => 'core:text', 'properties' => []]],
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
                'style' => ['col-span' => ['md' => 6], 'display' => 'block'],
                'attributedSpecifications' => ['image' => 'core:product-image'],
            ]],
            self::ACCEPTED,
            '',
        ];

        yield 'an element carrying nothing but its two required keys' => [
            [['id' => 'root-1', 'component' => 'core:text']],
            self::ACCEPTED,
            '',
        ];

        yield 'a data requirement whose key falls back to its map key' => [
            self::forest(['dataRequirements' => [
                'products' => ['source' => 'entity', 'config' => ['entity' => 'product', 'property' => 'productId']],
            ]]),
            self::ACCEPTED,
            '',
        ];

        yield 'a consumer with a property alias and no redistribution' => [
            self::forest(['acceptsContext' => [
                'items' => ['type' => 'single', 'required' => false, 'propertyAlias' => 'entry'],
            ]]),
            self::ACCEPTED,
            '',
        ];

        // A null passes Symfony's Type, Collection and All untouched, so every site whose decode counterpart
        // requires a value needs its own null rejection.
        yield 'a null root element' => [[null], self::REJECTED, ''];

        yield 'a null slot children list' => [
            self::forest(['slots' => ['main' => null]]),
            self::REJECTED,
            '',
        ];

        yield 'a null nested slot child' => [
            self::forest(['slots' => ['main' => [null]]]),
            self::REJECTED,
            '',
        ];

        yield 'a null data requirement entry' => [
            self::forest(['dataRequirements' => ['products' => null]]),
            self::REJECTED,
            '',
        ];

        yield 'a null context provider entry' => [
            self::forest(['providesContext' => ['product' => null]]),
            self::REJECTED,
            '',
        ];

        yield 'a null context consumer entry' => [
            self::forest(['acceptsContext' => ['items' => null]]),
            self::REJECTED,
            '',
        ];

        yield 'a null required flag on a consumer' => [
            self::forest(['acceptsContext' => ['items' => ['type' => 'single', 'required' => null]]]),
            self::REJECTED,
            '',
        ];

        yield 'a null attributed specification value' => [
            self::forest(['attributedSpecifications' => ['image' => null]]),
            self::REJECTED,
            '',
        ];

        // PHP maps a numeric JSON member name back to an integer array key, which no wiring map may carry.
        yield 'a numeric key in properties' => [
            self::forest(['properties' => [12 => 'x']]),
            self::REJECTED,
            '',
        ];

        yield 'a numeric key in dataRequirements' => [
            self::forest(['dataRequirements' => [
                7 => ['source' => 'entity', 'config' => ['entity' => 'product', 'property' => 'productId']],
            ]]),
            self::REJECTED,
            '',
        ];

        yield 'a numeric key in slots' => [
            self::forest(['slots' => [3 => [['id' => 'child-1', 'component' => 'core:text']]]]),
            self::REJECTED,
            '',
        ];

        yield 'a numeric key in providesContext' => [
            self::forest(['providesContext' => [0 => ['type' => 'single', 'distribution' => 'broadcast']]]),
            self::REJECTED,
            '',
        ];

        yield 'a numeric key in acceptsContext' => [
            self::forest(['acceptsContext' => [0 => ['type' => 'single', 'required' => true]]]),
            self::REJECTED,
            '',
        ];

        yield 'a numeric key in attributedSpecifications' => [
            self::forest(['attributedSpecifications' => [0 => 'core:product-image']]),
            self::REJECTED,
            '',
        ];

        yield 'a numeric key in a data requirement config' => [
            self::forest(['dataRequirements' => [
                'products' => ['source' => 'entity', 'config' => [0 => 'product']],
            ]]),
            self::REJECTED,
            '',
        ];

        yield 'a numeric key in a context provider entry' => [
            self::forest(['providesContext' => [
                'product' => ['type' => 'single', 'distribution' => 'broadcast', 0 => 'x'],
            ]]),
            self::REJECTED,
            '',
        ];

        yield 'a consumer alias without redistribution' => [
            self::forest(['acceptsContext' => [
                'items' => ['type' => 'single', 'required' => true, 'consumerAlias' => 'inner'],
            ]]),
            self::REJECTED,
            '',
        ];

        yield 'a property alias carrying dot notation' => [
            self::forest(['acceptsContext' => [
                'items' => ['type' => 'single', 'required' => true, 'propertyAlias' => 'product.name'],
            ]]),
            self::REJECTED,
            '',
        ];

        yield 'a forest whose root keys are not a sequential list' => [
            [
                0 => ['id' => 'root-1', 'component' => 'core:text'],
                2 => ['id' => 'root-2', 'component' => 'core:text'],
            ],
            self::REJECTED,
            '',
        ];

        yield 'slot children whose keys are not a sequential list' => [
            self::forest(['slots' => ['main' => [
                0 => ['id' => 'child-1', 'component' => 'core:text'],
                2 => ['id' => 'child-2', 'component' => 'core:text'],
            ]]]),
            self::REJECTED,
            '',
        ];

        yield 'a non-array properties' => [self::forest(['properties' => 'x']), self::REJECTED, ''];

        yield 'a non-array dataRequirements' => [self::forest(['dataRequirements' => 'x']), self::REJECTED, ''];

        yield 'a non-array slots' => [self::forest(['slots' => 'x']), self::REJECTED, ''];

        yield 'a non-array providesContext' => [self::forest(['providesContext' => 'x']), self::REJECTED, ''];

        yield 'a non-array acceptsContext' => [self::forest(['acceptsContext' => 'x']), self::REJECTED, ''];

        yield 'a non-array style' => [self::forest(['style' => 'x']), self::REJECTED, ''];

        yield 'a non-array attributedSpecifications' => [
            self::forest(['attributedSpecifications' => 'x']),
            self::REJECTED,
            '',
        ];

        yield 'an unknown element key' => [self::forest(['elements' => []]), self::REJECTED, ''];

        yield 'an unknown key in a data requirement entry' => [
            self::forest(['dataRequirements' => ['products' => [
                'source' => 'entity',
                'config' => ['entity' => 'product', 'property' => 'productId'],
                'limit' => 5,
            ]]]),
            self::REJECTED,
            '',
        ];

        yield 'an unknown key in a consumer entry' => [
            self::forest(['acceptsContext' => [
                'items' => ['type' => 'single', 'required' => true, 'fallback' => 'none'],
            ]]),
            self::REJECTED,
            '',
        ];

        yield 'a style option the registry does not know' => [
            self::forest(['style' => ['removed-plugin-option' => ['md' => 2]]]),
            self::DESCRIPTOR_ONLY,
            'the registry-aware check is a write-boundary rule; decode keeps an unknown option verbatim so '
            . 'that removing a style option provider does not make an already-stored layout unreadable',
        ];

        // Structural style defects are rejected by both sides: decode no longer cleans them away, because the
        // write path decodes before the constraint pass judges the tree, so cleaning let a malformed style
        // through with the offending part silently stripped. Registry-driven leniency is unaffected — see the
        // unknown-option row above, which decode still reads.
        yield 'a numeric style option name' => [
            self::forest(['style' => [0 => 'block']]),
            self::REJECTED,
            '',
        ];

        yield 'an empty breakpoint map on a style option' => [
            self::forest(['style' => ['col-span' => []]]),
            self::REJECTED,
            '',
        ];

        yield 'a non-scalar breakpoint value on a style option' => [
            self::forest(['style' => ['col-span' => ['md' => ['6']]]]),
            self::REJECTED,
            '',
        ];

        yield 'an unknown breakpoint key on a style option' => [
            self::forest(['style' => ['col-span' => ['bogus-breakpoint' => 6]]]),
            self::REJECTED,
            '',
        ];

        yield 'a style value that is neither scalar nor array' => [
            self::forest(['style' => ['col-span' => null]]),
            self::REJECTED,
            '',
        ];

        // A boolean option declares no NotBlank, so only an explicit NotNull per breakpoint keeps this row
        // out of the descriptor-accepts/codec-rejects direction the table forbids.
        yield 'a null breakpoint value on a boolean style option' => [
            self::forest(['style' => ['visible' => ['md' => null]]]),
            self::REJECTED,
            '',
        ];

        yield 'a boolean style option carrying false' => [
            self::forest(['style' => ['visible' => ['md' => false]]]),
            self::ACCEPTED,
            '',
        ];

        // The id value domain: both sides must state it, because `NotBlank` exempts '0' and `Type` admits the
        // reserved literal, so a descriptor-only rule would let a row persist that no later read can decode.
        yield 'the reserved virtual-root element id' => [
            [['id' => VirtualRootWrapper::VIRTUAL_ROOT_ID, 'component' => 'core:text']],
            self::REJECTED,
            '',
        ];

        yield 'an integer-castable element id' => [
            [['id' => '12', 'component' => 'core:text']],
            self::REJECTED,
            '',
        ];

        yield 'a blank element id' => [
            [['id' => '', 'component' => 'core:text']],
            self::DESCRIPTOR_ONLY,
            'NotBlank is a write-boundary rule against minting an unaddressable element; decode asks only for '
            . 'the type, so an already-stored blank id still reads',
        ];

        yield 'a blank element component' => [
            [['id' => 'root-1', 'component' => '']],
            self::DESCRIPTOR_ONLY,
            'NotBlank is a write-boundary rule against minting an element with no addressable type; decode '
            . 'asks only for component to be a string, so an already-stored blank component still reads',
        ];

        yield 'a keyed context provider with no key property' => [
            self::forest(['providesContext' => ['product' => ['type' => 'single', 'distribution' => 'keyed']]]),
            self::DESCRIPTOR_ONLY,
            'the strategy default lives in the distribution config value object, which fills the absent field '
            . 'on read; the write boundary asks for it explicitly',
        ];

        // Element nesting through slots: {@see StoredElementCodec::decodeElement()} counts a root as depth
        // zero and each slot descent as depth + 1, rejecting once depth exceeds MAX_NESTING_DEPTH.
        yield 'an element tree nested exactly at the depth bound' => [
            self::elementChain(StoredElementCodec::MAX_NESTING_DEPTH),
            self::ACCEPTED,
            '',
        ];

        yield 'an element tree nested one level past the depth bound' => [
            self::elementChain(StoredElementCodec::MAX_NESTING_DEPTH + 1),
            self::REJECTED,
            '',
        ];

        // An empty slot never recurses into a child, so it never reaches the depth guard, however deep it
        // sits: {@see StoredElementCodec} only ever evaluates that guard while recursing into an actual child.
        yield 'an element tree nested at the depth bound with an empty slot on the leaf' => [
            self::elementChain(
                StoredElementCodec::MAX_NESTING_DEPTH,
                ['id' => 'leaf', 'component' => 'core:text', 'slots' => ['main' => []]],
            ),
            self::ACCEPTED,
            '',
        ];

        yield 'an empty slot on an element below the depth bound' => [
            self::forest(['slots' => ['main' => []]]),
            self::ACCEPTED,
            '',
        ];

        // Property value nesting: {@see StoredElementCodec::decodeValue()} counts the property's own value as
        // depth zero and each array level below it as depth + 1, so a value with MAX_NESTING_DEPTH + 1 nested
        // arrays is the deepest one still accepted.
        yield 'a property value nested exactly at the depth bound' => [
            self::forest(['properties' => ['deep' => self::nestedPropertyValue(StoredElementCodec::MAX_NESTING_DEPTH + 1)]]),
            self::ACCEPTED,
            '',
        ];

        yield 'a property value nested one level past the depth bound' => [
            self::forest(['properties' => ['deep' => self::nestedPropertyValue(StoredElementCodec::MAX_NESTING_DEPTH + 2)]]),
            self::REJECTED,
            '',
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<array-key, mixed>
     */
    private static function forest(array $overrides): array
    {
        return [array_merge(['id' => 'root-1', 'component' => 'core:text'], $overrides)];
    }

    /**
     * A single-root forest whose deepest element sits at generation `$depth` below the root (root itself is
     * generation zero), each generation reached through one single-child `slots.main` descent — the same
     * shape {@see StoredElementCodec::decodeSlots()} walks one `decodeElement($child, $depth + 1)` call at a
     * time. `$leaf` is the element data placed at that deepest generation, a bare element by default.
     *
     * @param array<string, mixed> $leaf
     *
     * @return array<array-key, mixed>
     */
    private static function elementChain(int $depth, array $leaf = ['id' => 'leaf', 'component' => 'core:text']): array
    {
        $element = $leaf;

        for ($generation = $depth - 1; $generation >= 0; --$generation) {
            $element = [
                'id' => 'level-' . $generation,
                'component' => 'core:text',
                'slots' => ['main' => [$element]],
            ];
        }

        return [$element];
    }

    /**
     * A value nested `$arrayLevels` arrays deep around a scalar leaf — the same shape
     * {@see StoredElementCodec::decodeValue()} walks one array level at a time, counting the property's own
     * value as depth zero.
     */
    private static function nestedPropertyValue(int $arrayLevels): mixed
    {
        $value = 'leaf';

        for ($level = 0; $level < $arrayLevels; ++$level) {
            $value = [$value];
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $forest
     *
     * @return list<string>
     */
    private function descriptorViolations(array $forest): array
    {
        $validator = $this->validator();

        $messages = [];
        foreach ($validator->validate($forest, $this->constraints()->build()) as $violation) {
            $messages[] = $violation->getPropertyPath() . ': ' . (string) $violation->getMessage();
        }

        return $messages;
    }

    /**
     * The codec's verdict on one payload: `null` when it decodes, the rejection message when it does not.
     *
     * @param array<array-key, mixed> $forest
     */
    private function codecRejection(array $forest): ?string
    {
        try {
            $this->codec()->decode($forest);
        } catch (ContentSystemException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * Loader config semantics stay out of the comparison: the descriptor holds no config serializer, so it
     * cannot know what a given source's config must contain, and a provider that accepts any config keeps the
     * table about the wire shape both sides do own.
     */
    private function codec(): StoredTreeCodec
    {
        $configProvider = static::createStub(DataLoaderConfigSerializerProvider::class);
        $configProvider->method('decode')->willReturn(new StubLoaderConfig());

        return new StoredTreeCodec(new StoredElementCodec($configProvider));
    }

    /**
     * The descriptor attaches a constraint whose validator carries the element-type registry, so the default
     * factory (which builds every validator with `new`) cannot supply it. The registry here knows no type at
     * all, which keeps that rule inert: this table is about the wire shape both sides own, and what a declared
     * property type admits is neither the codec's business nor this table's.
     */
    private function validator(): ValidatorInterface
    {
        $typeRegistry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $typeRegistry->method('has')->willReturn(false);

        return Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                PropertyTypeConformanceValidator::class => new PropertyTypeConformanceValidator($typeRegistry),
            ]))
            ->getValidator();
    }

    private function constraints(): StoredTreeConstraints
    {
        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn([
            'col-span' => new StyleOptionSpecification(
                'col-span',
                new StyleOptionValueType('integer', null, ['min' => 1, 'max' => 12], null, null),
                true,
                null,
                'core'
            ),
            'display' => new StyleOptionSpecification(
                'display',
                new StyleOptionValueType('string', null, null, null, null),
                false,
                null,
                'core'
            ),
            // Breakpoint-aware AND boolean, mirroring the shipped `display`. Boolean carries no NotBlank by
            // design, so this is the option whose per-breakpoint null the descriptor would otherwise admit
            // while decode refuses it — a divergence a string- or integer-typed option cannot expose.
            'visible' => new StyleOptionSpecification(
                'visible',
                new StyleOptionValueType('boolean', null, null, null, null),
                true,
                null,
                'core'
            ),
        ]);

        return new StoredTreeConstraints($registry, new StyleOptionConstraintDeriver());
    }
}
