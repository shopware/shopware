<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Codec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeWiringConstraints;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * The element-local wiring tier and the provider distribution-field tier of the write descriptor. Every test
 * here reaches the rules through `StoredTreeConstraints::build()`, which is the only entry point the
 * composing descriptor exposes.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredTreeWiringConstraints::class)]
class StoredTreeWiringConstraintsTest extends StoredTreeConstraintsTestCase
{
    /**
     * The two redistribute rules emit at the same property path, so the wiring providers spell both messages
     * out rather than leaving the path to identify the rule.
     */
    private const DOTTED_PATH_MESSAGE = 'This context key uses dot notation and cannot be redistributed.';

    /**
     * @param array<string, mixed> $provider
     */
    #[DataProvider('acceptsDistributionProvider')]
    #[TestDox('reports no violation for $_dataName')]
    public function testAcceptsAWellFormedDistribution(array $provider): void
    {
        static::assertCount(0, $this->validate([$this->element(['providesContext' => ['product' => $provider]])]));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('acceptsCleanElementWiringProvider')]
    #[TestDox('reports no violation for $_dataName')]
    public function testAcceptsACleanElementWiringSibling(array $overrides): void
    {
        static::assertCount(0, $this->validate([$this->element($overrides)]));
    }

    /**
     * Where decode stops at the first rule it reaches, the descriptor owes every violation the element
     * carries: these are the fixtures whose codec counterpart pins the check order, and here both defects
     * must be reported so a fix of the first one does not hide the second until the next write.
     *
     * @param array<string, mixed> $overrides
     * @param list<string> $expectedPaths
     */
    #[DataProvider('reportsBothViolationsProvider')]
    #[TestDox('reports both violations for $_dataName')]
    public function testReportsEveryViolationOfADoublyDefectiveElement(array $overrides, array $expectedPaths): void
    {
        $violations = $this->validate([$this->element($overrides)]);

        $paths = array_values(array_map(
            static fn (ConstraintViolationInterface $violation): string => $violation->getPropertyPath(),
            iterator_to_array($violations)
        ));

        static::assertEqualsCanonicalizing($expectedPaths, $paths);
    }

    /**
     * The first holder of a base key is retained after a collision is reported, so every later consumer is
     * judged against it rather than against the consumer that collided just before. Two consumers cannot
     * show this: the path set is identical either way, and only the third consumer's reported holder differs.
     */
    #[TestDox('reports every colliding consumer against the first holder of the base key')]
    public function testReportsEveryCollisionAgainstTheFirstHolder(): void
    {
        $violations = $this->validate([$this->element(['acceptsContext' => [
            'product' => ['type' => 'single', 'required' => true],
            'category' => ['type' => 'single', 'required' => true, 'propertyAlias' => 'product'],
            'brand' => ['type' => 'single', 'required' => true, 'propertyAlias' => 'product'],
        ]])]);

        $messages = array_values(array_map(
            static fn (ConstraintViolationInterface $violation): string => (string) $violation->getMessage(),
            iterator_to_array($violations)
        ));

        static::assertSame(
            [
                'This consumer writes the property key product, which context product already writes.',
                'This consumer writes the property key product, which context product already writes.',
            ],
            $messages
        );
    }

    /**
     * Accumulation for the two redistribute rules: the descriptor owes one violation per offender, so a
     * callback that stopped at the first would report half of what the element actually carries.
     *
     * Reported as [path, message] pairs rather than bare paths. Both redistribute rules emit at
     * `[0][acceptsContext][<key>][redistribute]`, and canonicalizing drops order on top of that, so a
     * path-only set is identical whether each rule produced its own violation or one rule produced both
     * while the other went silent.
     *
     * @param array<string, mixed> $overrides
     * @param list<array{string, string}> $expectedViolations
     */
    #[DataProvider('accumulatesEveryElementLocalViolationProvider')]
    #[TestDox('reports one violation per offender for $_dataName')]
    public function testAccumulatesEveryElementLocalViolation(array $overrides, array $expectedViolations): void
    {
        $violations = $this->validate([$this->element($overrides)]);

        $reported = array_values(array_map(
            static fn (ConstraintViolationInterface $violation): array => [
                $violation->getPropertyPath(),
                (string) $violation->getMessage(),
            ],
            iterator_to_array($violations)
        ));

        static::assertEqualsCanonicalizing($expectedViolations, $reported);
    }

    /**
     * The no-op path every wiring rule takes for an explicitly empty map: each of
     * {@see StoredTreeWiringConstraints::validateConsumerBaseKeys()}, {@see StoredTreeWiringConstraints::validateRedistributeKeyShape()}
     * and {@see StoredTreeWiringConstraints::validateRedistributeProviderConflicts()} loops over the map and
     * exits without a single iteration, distinct from the field being absent entirely, which
     * {@see acceptsCleanElementWiringProvider()}'s rows never exercise.
     */
    #[TestDox('reports no violation for an element carrying explicitly empty wiring maps')]
    public function testAcceptsExplicitlyEmptyWiringMaps(): void
    {
        static::assertCount(0, $this->validate([$this->element(['providesContext' => [], 'acceptsContext' => []])]));
    }

    /**
     * A non-string `propertyAlias` is already reported by the field's own `Type('string')` constraint, so
     * {@see StoredTreeWiringConstraints::validateConsumerBaseKeys()} skips the entry rather than deriving a
     * base key from it — deriving one would call `str_contains()` on a non-string value under this file's
     * `strict_types=1` and fatally error the whole validation run instead of reporting cleanly.
     */
    #[TestDox('skips the base-key check for a consumer whose property alias is not a string')]
    public function testSkipsTheBaseKeyCheckForAMalformedPropertyAlias(): void
    {
        $violations = $this->validate([$this->element(['acceptsContext' => [
            'items' => ['type' => 'single', 'required' => true, 'propertyAlias' => ['nested']],
        ]])]);

        static::assertCount(1, $violations);
        static::assertSame('[0][acceptsContext][items][propertyAlias]', $violations->get(0)->getPropertyPath());
        static::assertSame('This value should be of type string.', (string) $violations->get(0)->getMessage());
    }

    /**
     * Same guard, applied by {@see StoredTreeWiringConstraints::validateRedistributeProviderConflicts()}: a
     * non-string `propertyAlias` is skipped rather than used to derive the provider key the consumer would
     * redistribute under — deriving one would pass a non-string key to `array_key_exists()`, which fatally
     * errors instead of the field's own `Type('string')` constraint reporting cleanly.
     */
    #[TestDox('skips the provider-conflict check for a consumer whose property alias is not a string')]
    public function testSkipsTheProviderConflictCheckForAMalformedPropertyAlias(): void
    {
        $violations = $this->validate([$this->element([
            'providesContext' => ['product' => ['type' => 'single', 'distribution' => 'broadcast']],
            'acceptsContext' => [
                'source' => ['type' => 'single', 'required' => true, 'redistribute' => true, 'propertyAlias' => ['nested']],
            ],
        ])]);

        static::assertCount(1, $violations);
        static::assertSame('[0][acceptsContext][source][propertyAlias]', $violations->get(0)->getPropertyPath());
        static::assertSame('This value should be of type string.', (string) $violations->get(0)->getMessage());
    }

    /**
     * The per-consumer combination tier: a consumer judged against itself, before any cross-map rule runs.
     * Separate from the element-local rows below, which mirror {@see StoredElementWiringDecoderTest} row for row.
     *
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('rejectsPerConsumerCombinationProvider')]
    #[TestDox('reports a violation at $expectedPath for $_dataName')]
    public function testRejectsAPerConsumerCombinationDefect(array $overrides, string $expectedPath, string $expectedMessage): void
    {
        $violations = $this->validate([$this->element($overrides)]);

        static::assertCount(1, $violations);
        static::assertSame($expectedPath, $violations->get(0)->getPropertyPath());
        static::assertSame($expectedMessage, (string) $violations->get(0)->getMessage());
    }

    /**
     * The element-local wiring tier, mirroring {@see StoredElementWiringDecoderTest} row for row: what decode throws
     * on, the descriptor reports, so a payload cannot pass the write and then fail every read.
     *
     * The message is asserted alongside the path because the two redistribute rules emit at the same path:
     * {@see StoredTreeWiringConstraints::validateRedistributeProviderConflicts()} and the dotted-key rule both
     * resolve to `[0][acceptsContext][<key>][redistribute]`, so a path-only assertion passes when the rule the
     * row names went silent and the other one misfired at that same path. The message is their only
     * discriminator.
     *
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('rejectsElementLocalWiringProvider')]
    #[TestDox('reports a violation at $expectedPath for $_dataName')]
    public function testRejectsAnElementLocalWiringDefect(array $overrides, string $expectedPath, string $expectedMessage): void
    {
        $violations = $this->validate([$this->element($overrides)]);

        static::assertCount(1, $violations);
        static::assertSame($expectedPath, $violations->get(0)->getPropertyPath());
        static::assertSame($expectedMessage, (string) $violations->get(0)->getMessage());
    }

    /**
     * @param array<string, mixed> $provider
     */
    #[DataProvider('rejectsDistributionProvider')]
    #[TestDox('reports a violation at $expectedPath for $_dataName')]
    public function testRejectsAMalformedDistribution(array $provider, string $expectedPath): void
    {
        $violations = $this->validate([$this->element(['providesContext' => ['product' => $provider]])]);

        static::assertCount(1, $violations);
        static::assertSame($expectedPath, $violations->get(0)->getPropertyPath());
    }

    /**
     * The map-level guard {@see StoredTreeWiringConstraints::validateStringKeys()} shares with the composing
     * descriptor's own copy: a numeric key inside either wiring map is reported once and does not also trip
     * the base-key or redistribute rules, which each skip a non-string context key rather than double-report.
     *
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('rejectsANonStringMapKeyProvider')]
    #[TestDox('reports a violation naming the offending key for $_dataName')]
    public function testRejectsANonStringMapKey(array $overrides): void
    {
        $violations = $this->validate([$this->element($overrides)]);

        static::assertCount(1, $violations);
        static::assertSame(
            'This map must be keyed by strings, 0 is not a string key.',
            (string) $violations->get(0)->getMessage()
        );
    }

    /**
     * The same guard, applied a second time by {@see StoredTreeWiringConstraints::contextProviderConstraints()}
     * to one provider's own config map, where the declared distribution strategy's extra fields live. Matches
     * {@see StoredElementWiringDecoderTest}'s decode-side counterpart for a numeric key inside a provider config.
     */
    #[TestDox('reports a violation naming the offending key for a numeric key inside a provider config')]
    public function testRejectsANonStringKeyInsideAProviderConfig(): void
    {
        $config = (array) json_decode(
            '{"type":"single","distribution":"broadcast","12":"x"}',
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        $violations = $this->validate([$this->element(['providesContext' => ['product' => $config]])]);

        static::assertCount(1, $violations);
        static::assertSame(
            'This map must be keyed by strings, 12 is not a string key.',
            (string) $violations->get(0)->getMessage()
        );
    }

    /**
     * A wiring container or entry of the wrong PHP type never reaches {@see StoredTreeWiringConstraints}'s own
     * violation-reporting guards for that value: {@see StoredTreeWiringConstraints::validateStringKeys()} runs
     * first and stays silent (its own `!is_array` early return), and the value's `Type` constraint plus the
     * `All`/`Collection` constraint composed alongside it each report their own type violation instead, so a
     * malformed container or entry is reported twice rather than once.
     *
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('rejectsAMalformedWiringTypeProvider')]
    #[TestDox('reports a violation for $_dataName')]
    public function testRejectsAMalformedWiringType(array $overrides, string $expectedTypeMessage): void
    {
        $violations = $this->validate([$this->element($overrides)]);

        $messages = array_values(array_map(
            static fn (ConstraintViolationInterface $violation): string => (string) $violation->getMessage(),
            iterator_to_array($violations)
        ));

        static::assertEqualsCanonicalizing(
            ['This value should be of type array.', $expectedTypeMessage],
            $messages
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function rejectsANonStringMapKeyProvider(): iterable
    {
        yield 'a non-string key inside providesContext' => [
            ['providesContext' => [0 => ['type' => 'single', 'distribution' => 'keyed', 'keyProperty' => 'sku']]],
        ];

        yield 'a non-string key inside acceptsContext' => [
            ['acceptsContext' => [0 => ['type' => 'single', 'required' => true]]],
        ];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function rejectsAMalformedWiringTypeProvider(): iterable
    {
        yield 'a non-array providesContext' => [
            ['providesContext' => 'not-an-array'],
            'This value should be of type iterable.',
        ];

        yield 'a non-array acceptsContext' => [
            ['acceptsContext' => 'not-an-array'],
            'This value should be of type iterable.',
        ];

        yield 'a non-array provider entry' => [
            ['providesContext' => ['product' => 'not-an-array']],
            'This value should be of type array|(Traversable&ArrayAccess).',
        ];

        yield 'a non-array consumer entry' => [
            ['acceptsContext' => ['items' => 'not-an-array']],
            'This value should be of type array|(Traversable&ArrayAccess).',
        ];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, string}>
     */
    public static function rejectsPerConsumerCombinationProvider(): iterable
    {
        yield 'a consumer alias declared without redistribution' => [
            ['acceptsContext' => [
                'items' => ['type' => 'single', 'required' => true, 'consumerAlias' => 'inner'],
            ]],
            '[0][acceptsContext][items][consumerAlias]',
            'This value requires "redistribute" to be true.',
        ];

        yield 'a property alias carrying dot notation' => [
            ['acceptsContext' => [
                'items' => ['type' => 'single', 'required' => true, 'propertyAlias' => 'product.name'],
            ]],
            '[0][acceptsContext][items][propertyAlias]',
            'This value should be a simple property name without dot notation.',
        ];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<array{string, string}>}>
     */
    public static function accumulatesEveryElementLocalViolationProvider(): iterable
    {
        // Base keys 'product' and 'category' are distinct, so the base-key rule stays silent and these
        // are exactly the two dotted-redistribute violations.
        yield 'two redistributing consumers keyed by dotted paths' => [
            ['acceptsContext' => [
                'product.manufacturer' => ['type' => 'single', 'required' => true, 'redistribute' => true],
                'category.parent' => ['type' => 'single', 'required' => true, 'redistribute' => true],
            ]],
            [
                ['[0][acceptsContext][product.manufacturer][redistribute]', self::DOTTED_PATH_MESSAGE],
                ['[0][acceptsContext][category.parent][redistribute]', self::DOTTED_PATH_MESSAGE],
            ],
        ];

        yield 'two redistributing consumers whose derived keys authored providers hold' => [
            [
                'providesContext' => [
                    'product' => ['type' => 'single', 'distribution' => 'broadcast'],
                    'category' => ['type' => 'single', 'distribution' => 'broadcast'],
                ],
                'acceptsContext' => [
                    'product' => ['type' => 'single', 'required' => true, 'redistribute' => true],
                    'category' => ['type' => 'single', 'required' => true, 'redistribute' => true],
                ],
            ],
            [
                ['[0][acceptsContext][product][redistribute]', self::providerConflictMessage('product')],
                ['[0][acceptsContext][category][redistribute]', self::providerConflictMessage('category')],
            ],
        ];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, string}>
     */
    public static function rejectsElementLocalWiringProvider(): iterable
    {
        yield 'two consumers sharing one base key' => [
            ['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => true],
                'category' => ['type' => 'single', 'required' => true, 'propertyAlias' => 'product'],
            ]],
            '[0][acceptsContext][category]',
            'This consumer writes the property key product, which context product already writes.',
        ];

        yield 'a redistributing consumer keyed by a dotted path' => [
            ['acceptsContext' => [
                'product.manufacturer' => ['type' => 'single', 'required' => true, 'redistribute' => true],
            ]],
            '[0][acceptsContext][product.manufacturer][redistribute]',
            self::DOTTED_PATH_MESSAGE,
        ];

        yield 'a redistributing consumer whose context key an authored provider holds' => [
            [
                'providesContext' => ['product' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => ['product' => ['type' => 'single', 'required' => true, 'redistribute' => true]],
            ],
            '[0][acceptsContext][product][redistribute]',
            self::providerConflictMessage('product'),
        ];

        // The derived provider key is the propertyAlias, so the reported key differs from the context key the
        // path names — the message is what shows the rule read the alias rather than the key.
        yield 'a redistributing consumer whose property alias an authored provider holds' => [
            [
                'providesContext' => ['product' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => [
                    'source' => ['type' => 'single', 'required' => true, 'redistribute' => true, 'propertyAlias' => 'product'],
                ],
            ],
            '[0][acceptsContext][source][redistribute]',
            self::providerConflictMessage('product'),
        ];
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function acceptsCleanElementWiringProvider(): iterable
    {
        yield 'two consumers writing distinct base keys' => [
            ['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => true],
                'category' => ['type' => 'single', 'required' => true, 'propertyAlias' => 'item'],
            ]],
        ];

        yield 'a redistributing consumer keyed by a base key' => [
            ['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => true, 'redistribute' => true],
            ]],
        ];

        yield 'a redistributing consumer beside a provider on another key' => [
            [
                'providesContext' => ['other' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => ['product' => ['type' => 'single', 'required' => true, 'redistribute' => true]],
            ],
        ];

        yield 'a redistributing consumer whose property alias no provider holds' => [
            [
                'providesContext' => ['product' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => [
                    'source' => ['type' => 'single', 'required' => true, 'redistribute' => true, 'propertyAlias' => 'productList'],
                ],
            ],
        ];

        yield 'a consumer alias equal to an authored provider key' => [
            [
                'providesContext' => [
                    'item' => ['type' => 'single', 'distribution' => 'broadcast', 'consumerAlias' => 'other'],
                ],
                'acceptsContext' => [
                    'product' => ['type' => 'single', 'required' => true, 'redistribute' => true, 'consumerAlias' => 'item'],
                ],
            ],
        ];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>}>
     */
    public static function reportsBothViolationsProvider(): iterable
    {
        yield 'a consumer renaming without redistributing while sharing a held base key' => [
            ['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => true],
                'category' => [
                    'type' => 'single',
                    'required' => true,
                    'consumerAlias' => 'inner',
                    'propertyAlias' => 'product',
                ],
            ]],
            ['[0][acceptsContext][category][consumerAlias]', '[0][acceptsContext][category]'],
        ];

        yield 'a redistributing consumer keyed by a dotted path onto a held base key' => [
            ['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => true],
                'product.manufacturer' => ['type' => 'single', 'required' => true, 'redistribute' => true],
            ]],
            [
                '[0][acceptsContext][product.manufacturer]',
                '[0][acceptsContext][product.manufacturer][redistribute]',
            ],
        ];

        yield 'an earlier provider conflict beside a later dotted property alias' => [
            [
                'providesContext' => ['early' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => [
                    'early' => ['type' => 'single', 'required' => true, 'redistribute' => true],
                    'late' => ['type' => 'single', 'required' => true, 'propertyAlias' => 'nested.key'],
                ],
            ],
            ['[0][acceptsContext][early][redistribute]', '[0][acceptsContext][late][propertyAlias]'],
        ];
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function acceptsDistributionProvider(): iterable
    {
        yield 'a broadcast provider carrying nothing beyond its two declared fields' => [
            ['type' => 'single', 'distribution' => 'broadcast'],
        ];

        yield 'an indexed provider carrying nothing beyond its two declared fields' => [
            ['type' => 'single', 'distribution' => 'indexed'],
        ];

        yield 'an iterator provider carrying nothing beyond its two declared fields' => [
            ['type' => 'collection', 'distribution' => 'iterator'],
        ];

        yield 'a keyed provider carrying its key property' => [
            ['type' => 'collection', 'distribution' => 'keyed', 'keyProperty' => 'sku'],
        ];

        yield 'a sliced provider carrying its slice size' => [
            ['type' => 'collection', 'distribution' => 'sliced', 'sliceSize' => 5],
        ];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function rejectsDistributionProvider(): iterable
    {
        yield 'a provider declaring no distribution, whose strategy fields are skipped' => [
            ['type' => 'single'],
            '[0][providesContext][product][distribution]',
        ];

        yield 'a provider declaring an unknown distribution, whose strategy fields are skipped' => [
            ['type' => 'single', 'distribution' => 'unknown'],
            '[0][providesContext][product][distribution]',
        ];

        // One row covers the shared `consumerAlias` rule: broadcast, indexed and iterator each declare the
        // byte-identical constraint set, so a second and third strategy assert nothing the first does not.
        // The keyed and sliced rows below stay, because their configs declare an additional field each.
        yield 'a broadcast provider whose consumer alias is not a string' => [
            ['type' => 'single', 'distribution' => 'broadcast', 'consumerAlias' => 42],
            '[0][providesContext][product][consumerAlias]',
        ];

        yield 'a keyed provider with no key property' => [
            ['type' => 'collection', 'distribution' => 'keyed'],
            '[0][providesContext][product][keyProperty]',
        ];

        yield 'a sliced provider with no slice size' => [
            ['type' => 'collection', 'distribution' => 'sliced'],
            '[0][providesContext][product][sliceSize]',
        ];
    }

    /**
     * The provider-conflict message names the key the consumer derives, which is its propertyAlias when it
     * carries one and its context key otherwise, so the rows pass the derived key rather than restating it.
     */
    private static function providerConflictMessage(string $derivedProviderKey): string
    {
        return \sprintf(
            'This consumer redistributes under the provider key %s, which an authored provider already holds.',
            $derivedProviderKey
        );
    }
}
