<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnoreEvaluationRule;

/**
 * @internal
 *
 * @extends RuleTestCase<CodeCoverageIgnoreEvaluationRule>
 */
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

        yield 'exception subclass with logic is flagged like any other class' => [
            ['ExceptionWithLogicClass.php'],
            [[
                'Class ' . self::FQCN_PREFIX . 'ExceptionWithLogicClass is annotated @codeCoverageIgnore but method describe() contains logic. Remove the annotation, extract the logic to a covered class, or add a @see pointing to an existing integration test that exercises it.',
                10,
            ]],
        ];

        yield '@see pointing to existing integration test exempts the class' => [
            ['SeeExistingIntegrationTestClass.php'],
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
    }

    protected function getRule(): Rule
    {
        return new CodeCoverageIgnoreEvaluationRule(self::createReflectionProvider());
    }
}
