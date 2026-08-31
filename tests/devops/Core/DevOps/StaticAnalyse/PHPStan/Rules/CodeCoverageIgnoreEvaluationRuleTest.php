<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnoreEvaluationRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<CodeCoverageIgnoreEvaluationRule>
 */
#[Package('framework')]
class CodeCoverageIgnoreEvaluationRuleTest extends RuleTestCase
{
    private const FIXTURE_DIR = __DIR__ . '/data/CodeCoverageIgnoreEvaluation/';
    private const FQCN_PREFIX = 'Shopware\\Tests\\DevOps\\Core\\DevOps\\StaticAnalyse\\PHPStan\\Rules\\data\\CodeCoverageIgnoreEvaluation\\';

    /**
     * @param list<string> $fixtures
     * @param list<array{0: string, 1: int}> $expectedErrors
     */
    #[TestDox('@codeCoverageIgnore on $_dataName')]
    #[DataProvider('caseProvider')]
    public function testRule(array $fixtures, array $expectedErrors): void
    {
        $files = array_map(static fn (string $f) => self::FIXTURE_DIR . $f, $fixtures);

        $this->analyse($files, $expectedErrors);
    }

    /**
     * @return \Generator<string, array{0: list<string>, 1: list<array{0: string, 1: int}>}>
     */
    public static function caseProvider(): \Generator
    {
        yield 'pure getters and setters passes' => [['PureGetterClass.php'], []];

        yield 'DTO with no methods passes' => [['EmptyDtoClass.php'], []];

        yield 'constructor with only promoted properties passes' => [['PromotedConstructorClass.php'], []];

        yield 'method-level ignore on pure getter passes' => [['MethodLevelIgnoreOnPureGetterClass.php'], []];

        yield 'no annotation but logic present passes' => [['IgnoreStartEndOnlyClass.php'], []];

        yield 'exception fork branching is not logic, class ignore passes' => [['ExceptionForkClass.php'], []];

        yield 'exception with loop aggregation still fails' => [
            ['ExceptionWithAggregationClass.php'],
            [
                [
                    'Class ' . self::FQCN_PREFIX . 'ExceptionWithAggregationClass is annotated @codeCoverageIgnore but method aggregate() contains logic. Remove the annotation, extract the logic to a covered class, or add a @see pointing to an existing integration test that exercises it.',
                    18,
                ],
            ],
        ];

        yield 'class with logic method fails' => [
            ['IfBranchClass.php'],
            [[
                'Class ' . self::FQCN_PREFIX . 'IfBranchClass is annotated @codeCoverageIgnore but method describe() contains logic. Remove the annotation, extract the logic to a covered class, or add a @see pointing to an existing integration test that exercises it.',
                10,
            ]],
        ];

        yield 'validating constructor fails' => [
            ['ValidatingConstructorClass.php'],
            [[
                'Class ' . self::FQCN_PREFIX . 'ValidatingConstructorClass is annotated @codeCoverageIgnore but method __construct() contains logic. Remove the annotation, extract the logic to a covered class, or add a @see pointing to an existing integration test that exercises it.',
                10,
            ]],
        ];

        yield 'method-level ignore on logic method fails' => [
            ['MethodLevelIgnoreOnLogicClass.php'],
            [[
                'Method ' . self::FQCN_PREFIX . 'MethodLevelIgnoreOnLogicClass::withLogic() is annotated @codeCoverageIgnore but contains logic. Remove the annotation, extract the logic to a covered method, or add a @see pointing to an existing integration test that exercises it.',
                15,
            ]],
        ];

        yield 'arithmetic alone is not logic' => [
            ['BinaryOpClass.php'],
            [],
        ];

        yield 'coalesce (??) alone is not logic' => [
            ['CoalesceClass.php'],
            [],
        ];

        yield 'magic method with logic fails' => [
            ['MagicMethodClass.php'],
            [[
                'Class ' . self::FQCN_PREFIX . 'MagicMethodClass is annotated @codeCoverageIgnore but method __get() contains logic. Remove the annotation, extract the logic to a covered class, or add a @see pointing to an existing integration test that exercises it.',
                15,
            ]],
        ];

        yield 'abstract class with concrete logic fails' => [
            ['AbstractWithConcreteLogicClass.php'],
            [[
                'Class ' . self::FQCN_PREFIX . 'AbstractWithConcreteLogicClass is annotated @codeCoverageIgnore but method decorate() contains logic. Remove the annotation, extract the logic to a covered class, or add a @see pointing to an existing integration test that exercises it.',
                12,
            ]],
        ];

        yield 'exception subclass without logic passes' => [['ExceptionWithoutLogicClass.php'], []];

        yield 'exception subclass with plain conditionals passes (throwable exemption)' => [['ExceptionWithLogicClass.php'], []];

        yield '@see pointing to existing integration test exempts the class' => [
            ['SeeExistingIntegrationTestClass.php'],
            [],
        ];

        yield '@see pointing to existing devops test exempts the class' => [
            ['SeeDevOpsTestClass.php'],
            [],
        ];

        yield '@see short-form resolved via use statement also exempts' => [
            ['SeeShortFormIntegrationTestClass.php'],
            [],
        ];

        yield '@see pointing to missing integration test does not exempt' => [
            ['SeeMissingIntegrationTestClass.php'],
            [[
                'Class ' . self::FQCN_PREFIX . 'SeeMissingIntegrationTestClass is annotated @codeCoverageIgnore but method describe() contains logic. Remove the annotation, extract the logic to a covered class, or add a @see pointing to an existing integration test that exercises it.',
                14,
            ]],
        ];

        yield '@see pointing to unit test (not integration) does not exempt' => [
            ['SeeUnitTestClass.php'],
            [[
                'Class ' . self::FQCN_PREFIX . 'SeeUnitTestClass is annotated @codeCoverageIgnore but method describe() contains logic. Remove the annotation, extract the logic to a covered class, or add a @see pointing to an existing integration test that exercises it.',
                14,
            ]],
        ];

        yield 'method-level @see to integration test exempts that method' => [
            ['MethodSeeIntegrationTestClass.php'],
            [],
        ];

        yield 'delegating getter chain rooted at $this is not logic' => [
            ['DelegatingGetterClass.php'],
            [],
        ];

        yield 'delegation with arguments is not logic on its own' => [
            ['DelegatingWithArgsClass.php'],
            [],
        ];

        yield 'Feature::triggerDeprecationOrThrow is not logic' => [
            ['FeatureDeprecationOnlyClass.php'],
            [],
        ];

        yield 'plain function calls are not logic on their own' => [
            ['OtherStaticCallClass.php'],
            [],
        ];

        yield 'parent::__construct chaining is not logic' => [
            ['ParentChainConstructorParent.php', 'ParentChainConstructorClass.php'],
            [],
        ];

        yield 'method calls inside parent::__construct args do not count as logic' => [
            ['ParentChainConstructorParent.php', 'ParentChainWithCallArgClass.php'],
            [],
        ];

        yield 'discarded call on a parameter fails' => [
            ['ParameterMutationClass.php'],
            [[
                'Class ' . self::FQCN_PREFIX . 'ParameterMutationClass is annotated @codeCoverageIgnore but method processCriteria() contains logic. Remove the annotation, extract the logic to a covered class, or add a @see pointing to an existing integration test that exercises it.',
                10,
            ]],
        ];

        yield 'entity extension adding fields to the passed collection passes' => [
            ['SchemaExtensionClass.php'],
            [],
        ];

        yield 'named constructor initialising a fresh local passes' => [
            ['NamedConstructorClass.php'],
            [],
        ];

        yield 'literal handed to the parent constructor fails' => [
            ['ParentChainConstructorParent.php', 'ParentLiteralConfigClass.php'],
            [[
                'Class ' . self::FQCN_PREFIX . 'ParentLiteralConfigClass is annotated @codeCoverageIgnore but method __construct() contains logic. Remove the annotation, extract the logic to a covered class, or add a @see pointing to an existing integration test that exercises it.',
                10,
            ]],
        ];

        yield 'constructor default equal to the parent default passes' => [
            ['DefaultingConstructorParent.php', 'ChildSameDefaultClass.php'],
            [],
        ];

        yield 'constructor default differing from the parent default fails' => [
            ['DefaultingConstructorParent.php', 'ChildDefaultOverrideClass.php'],
            [[
                'Class ' . self::FQCN_PREFIX . 'ChildDefaultOverrideClass is annotated @codeCoverageIgnore but method __construct() contains logic. Remove the annotation, extract the logic to a covered class, or add a @see pointing to an existing integration test that exercises it.',
                10,
            ]],
        ];

        yield 'method-level ignore on a constructor default override fails' => [
            ['DefaultingConstructorParent.php', 'MethodLevelDefaultOverrideClass.php'],
            [[
                'Method ' . self::FQCN_PREFIX . 'MethodLevelDefaultOverrideClass::__construct() is annotated @codeCoverageIgnore but contains logic. Remove the annotation, extract the logic to a covered method, or add a @see pointing to an existing integration test that exercises it.',
                10,
            ]],
        ];

        yield 'constructor default the parent does not have fails' => [
            ['DefaultingConstructorParent.php', 'ChildIntroducesDefaultClass.php'],
            [[
                'Class ' . self::FQCN_PREFIX . 'ChildIntroducesDefaultClass is annotated @codeCoverageIgnore but method __construct() contains logic. Remove the annotation, extract the logic to a covered class, or add a @see pointing to an existing integration test that exercises it.',
                10,
            ]],
        ];

        yield 'array offset assignment ($this->arr[$k] = $v) is not logic' => [
            ['ArraySetterClass.php'],
            [],
        ];

        yield 'array offset getter with null-coalesce fallback is not logic' => [
            ['ArrayCoalesceGetterClass.php'],
            [],
        ];

        yield 'static configuration construction with json_encode and self:: dispatch is not logic' => [
            ['ConfigConstructionClass.php'],
            [],
        ];

        yield 'single write to a local and destructuring into fresh locals are not logic' => [
            ['SingleLocalWriteClass.php'],
            [],
        ];

        yield 'unset on a value fails' => [
            ['UnsetMutationClass.php'],
            [[
                'Class ' . self::FQCN_PREFIX . 'UnsetMutationClass is annotated @codeCoverageIgnore but method strip() contains logic. Remove the annotation, extract the logic to a covered class, or add a @see pointing to an existing integration test that exercises it.',
                15,
            ]],
        ];

        yield 'compound assignment fails' => [
            ['CompoundAssignClass.php'],
            [[
                'Class ' . self::FQCN_PREFIX . 'CompoundAssignClass is annotated @codeCoverageIgnore but method merge() contains logic. Remove the annotation, extract the logic to a covered class, or add a @see pointing to an existing integration test that exercises it.',
                16,
            ]],
        ];

        yield 'getter running an access guard on $this fails' => [
            ['GuardedGetterClass.php'],
            [
                [
                    'Class ' . self::FQCN_PREFIX . 'GuardedGetterClass is annotated @codeCoverageIgnore but method getPassword() contains logic. Remove the annotation, extract the logic to a covered class, or add a @see pointing to an existing integration test that exercises it.',
                    12,
                ],
                [
                    'Class ' . self::FQCN_PREFIX . 'GuardedGetterClass is annotated @codeCoverageIgnore but method checkIfPropertyAccessIsAllowed() contains logic. Remove the annotation, extract the logic to a covered class, or add a @see pointing to an existing integration test that exercises it.',
                    19,
                ],
            ],
        ];

        yield 'second write to a local fails' => [
            ['LocalReassignClass.php'],
            [[
                'Class ' . self::FQCN_PREFIX . 'LocalReassignClass is annotated @codeCoverageIgnore but method build() contains logic. Remove the annotation, extract the logic to a covered class, or add a @see pointing to an existing integration test that exercises it.',
                13,
            ]],
        ];
    }

    protected function getRule(): Rule
    {
        return new CodeCoverageIgnoreEvaluationRule(self::createReflectionProvider());
    }
}
