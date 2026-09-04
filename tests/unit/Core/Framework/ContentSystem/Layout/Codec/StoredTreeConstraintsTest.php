<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Codec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeConstraints;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeWiringConstraints;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionConstraintDeriver;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * The element, slot, style and provider-field tiers of the write descriptor. The element-local wiring tier
 * sits in {@see StoredTreeWiringConstraintsTest}; both files share {@see StoredTreeConstraintsTestCase}.
 *
 * The provider tests below reach {@see StoredTreeWiringConstraints}, which the descriptor composes, which is
 * why it is covered here too.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredTreeConstraints::class)]
#[CoversClass(StoredTreeWiringConstraints::class)]
class StoredTreeConstraintsTest extends StoredTreeConstraintsTestCase
{
    #[TestDox('reports no violation for a well-formed forest carrying every element field')]
    public function testValidatesAWellFormedForest(): void
    {
        $forest = [
            [
                'id' => 'root-1',
                'component' => 'core:section',
                'properties' => ['title' => 'Hello'],
                'dataRequirements' => [
                    'products' => ['key' => 'products', 'source' => 'entity', 'config' => []],
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
                    'items' => ['type' => 'collection', 'required' => true],
                ],
                'style' => ['col-span' => ['md' => 6]],
                'attributedSpecifications' => ['image' => 'core:product-image'],
            ],
        ];

        static::assertCount(0, $this->validate($forest));
    }

    #[TestDox('attaches the property-type rule to every element, reporting a root and a nested child at their own paths')]
    public function testAttachesThePropertyTypeRuleToEveryElement(): void
    {
        // Both elements carry a value the registered type declares as a string. The rule is registry-aware and
        // lives in its own validator, so nothing but the descriptor attaching it to each element's constraint
        // list can produce these two violations — a root-only attachment leaves the child path unreported.
        $violations = $this->validate([
            [
                'id' => 'root-1',
                'component' => 'core:text',
                'properties' => ['headline' => 42],
                'slots' => [
                    'main' => [
                        ['id' => 'child-1', 'component' => 'core:text', 'properties' => ['headline' => ['a', 'b']]],
                    ],
                ],
            ],
        ]);

        $paths = array_values(array_map(
            static fn (ConstraintViolationInterface $violation): string => $violation->getPropertyPath(),
            iterator_to_array($violations)
        ));

        static::assertEqualsCanonicalizing(
            ['[0][properties][headline]', '[0][slots][main][0].[properties][headline]'],
            $paths
        );
    }

    /**
     * @param array<string, mixed> $style
     */
    #[DataProvider('acceptsStyleProvider')]
    #[TestDox('reports no violation for $_dataName')]
    public function testAcceptsAValidStyle(array $style): void
    {
        static::assertCount(0, $this->validate([$this->element(['style' => $style])]));
    }

    /**
     * @param array<string, mixed> $provider
     */
    #[DataProvider('acceptsDistributionProvider')]
    #[TestDox('reports no violation for $_dataName')]
    public function testAcceptsAWellFormedDistribution(array $provider): void
    {
        static::assertCount(0, $this->validate([$this->element(['providesContext' => ['product' => $provider]])]));
    }

    #[TestDox('derives the style constraints fresh on each call so a changed registry reaches the next write')]
    public function testDerivesStyleConstraintsFreshPerCall(): void
    {
        // An app install/update/activation that changed the option set must take effect on the next write
        // without a process restart, so each build() re-reads the registry and never memoizes. The registry
        // below has no `display` option on the first read and only that option on the second, so the same
        // payload is rejected and then accepted; assertCount(0, $afterChange) fails if anything is memoized.
        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturnOnConsecutiveCalls(
            ['col-span' => new StyleOptionSpecification('col-span', new StyleOptionValueType('integer', null, ['min' => 1, 'max' => 12], null, null), true, null, 'core')],
            ['display' => new StyleOptionSpecification('display', new StyleOptionValueType('boolean', null, null, null, null), true, null, 'core')],
        );

        $constraints = new StoredTreeConstraints($registry, new StyleOptionConstraintDeriver());
        $validator = $this->validator();
        $forest = [$this->element(['style' => ['display' => ['xs' => false]]])];

        $beforeChange = $validator->validate($forest, $constraints->build());
        $afterChange = $validator->validate($forest, $constraints->build());

        static::assertGreaterThanOrEqual(1, $beforeChange->count());
        static::assertCount(0, $afterChange);
    }

    #[TestDox('reports only the missing-field violation for a provider that declares no distribution')]
    public function testSkipsTheDistributionFieldsWhenNoDistributionIsDeclared(): void
    {
        $violations = $this->validate([$this->element(['providesContext' => ['product' => ['type' => 'single']]])]);

        static::assertCount(1, $violations);
        static::assertSame('[0][providesContext][product][distribution]', $violations->get(0)->getPropertyPath());
    }

    #[TestDox('reports only the invalid-choice violation for a provider declaring an unknown distribution')]
    public function testSkipsTheDistributionFieldsForAnUnknownDistribution(): void
    {
        $provider = ['type' => 'single', 'distribution' => 'unknown'];

        $violations = $this->validate([$this->element(['providesContext' => ['product' => $provider]])]);

        static::assertCount(1, $violations);
        static::assertSame('[0][providesContext][product][distribution]', $violations->get(0)->getPropertyPath());
    }

    #[TestDox('reaches a nested slot child and reports its violation at a path identifying that child')]
    public function testReportsAViolationOnANestedSlotChild(): void
    {
        $violations = $this->validate([
            [
                'id' => 'root-1',
                'component' => 'core:section',
                'properties' => [],
                'slots' => [
                    'main' => [
                        ['id' => 'child-1', 'component' => 'core:section', 'properties' => [], 'slots' => [
                            'inner' => [
                                ['id' => '', 'component' => 'core:text', 'properties' => []],
                            ],
                        ]],
                    ],
                ],
            ],
        ]);

        static::assertCount(1, $violations);
        static::assertSame(
            '[0][slots][main][0].[slots][inner][0].[id]',
            $violations->get(0)->getPropertyPath()
        );
    }

    #[TestDox('reports a violation for a style option the registry does not know')]
    public function testRejectsAnUnknownStyleOption(): void
    {
        $violations = $this->validate([
            [
                'id' => 'root-1',
                'component' => 'core:text',
                'properties' => [],
                'style' => ['removed-plugin-option' => ['md' => 2]],
            ],
        ]);

        static::assertCount(1, $violations);
        static::assertSame('[0][style][removed-plugin-option]', $violations->get(0)->getPropertyPath());
    }

    #[TestDox('reports a violation for a forest whose root keys are not a sequential list')]
    public function testRejectsANonSequentialForest(): void
    {
        $validator = $this->validator();

        $forest = [
            0 => ['id' => 'root-1', 'component' => 'core:text', 'properties' => []],
            2 => ['id' => 'root-2', 'component' => 'core:text', 'properties' => []],
        ];

        $violations = $validator->validate($forest, $this->constraints()->build());

        static::assertGreaterThanOrEqual(1, $violations->count());
    }

    #[TestDox('reports a violation for a slot whose children keys are not a sequential list')]
    public function testRejectsANonSequentialSlotChildrenArray(): void
    {
        $violations = $this->validate([
            [
                'id' => 'root-1',
                'component' => 'core:section',
                'properties' => [],
                'slots' => [
                    'main' => [
                        0 => ['id' => 'child-1', 'component' => 'core:text', 'properties' => []],
                        2 => ['id' => 'child-2', 'component' => 'core:text', 'properties' => []],
                    ],
                ],
            ],
        ]);

        static::assertGreaterThanOrEqual(1, $violations->count());
    }

    #[TestDox('reports a violation naming the offending key for a numeric key in a wiring map')]
    public function testRejectsANumericWiringMapKey(): void
    {
        $violations = $this->validate([
            [
                'id' => 'root-1',
                'component' => 'core:text',
                'properties' => [12 => 'x'],
            ],
        ]);

        static::assertCount(1, $violations);
        static::assertSame(
            'This map must be keyed by strings, 12 is not a string key.',
            (string) $violations->get(0)->getMessage()
        );
    }

    /**
     * @param array<string, mixed> $style
     */
    #[DataProvider('rejectsStyleProvider')]
    #[TestDox('reports a violation at $expectedPath for $_dataName')]
    public function testRejectsAnInvalidStyle(array $style, string $expectedPath): void
    {
        $violations = $this->validate([$this->element(['style' => $style])]);

        static::assertGreaterThanOrEqual(1, $violations->count());
        // The path proves the violation fires on the offending option/breakpoint, not a stray top-level one
        static::assertSame($expectedPath, $violations->get(0)->getPropertyPath());
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
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function acceptsStyleProvider(): iterable
    {
        yield 'an integer span within its declared range' => [['col-span' => ['md' => 6]]];
        yield 'a boolean option set to false' => [['display' => ['xs' => false]]];
        yield 'an enum value from the option vocabulary' => [['align-self' => ['lg' => 'center']]];
        yield 'a string within its declared maxLength' => [['margin' => ['md' => '0 8px']]];
        yield 'an empty style' => [[]];
        yield 'a flat integer option sent as a scalar' => [['z-index' => 10]];
        yield 'a flat string option within its maxLength' => [['flat-label' => 'short']];
        yield 'coexisting flat and breakpoint-aware options' => [['z-index' => 10, 'col-span' => ['md' => 6]]];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function rejectsStyleProvider(): iterable
    {
        yield 'an empty breakpoint map' => [['col-span' => []], '[0][style][col-span]'];
        yield 'an unknown breakpoint key' => [['col-span' => ['zz' => 6]], '[0][style][col-span][zz]'];
        yield 'an integer outside the declared range' => [['col-span' => ['md' => 99]], '[0][style][col-span][md]'];
        yield 'a non-integer value for an integer option' => [['col-span' => ['md' => 'six']], '[0][style][col-span][md]'];
        yield 'an enum value outside the vocabulary' => [['align-self' => ['md' => 'sideways']], '[0][style][align-self][md]'];
        yield 'a string exceeding its maxLength' => [['margin' => ['md' => 'this-value-is-way-too-long']], '[0][style][margin][md]'];
        yield 'a flat integer option sent as a breakpoint map' => [['z-index' => ['md' => 10]], '[0][style][z-index]'];
        yield 'a breakpoint-aware option sent as a bare scalar' => [['col-span' => 6], '[0][style][col-span]'];
        yield 'a flat string option exceeding its maxLength' => [['flat-label' => '123456789'], '[0][style][flat-label]'];
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
}
