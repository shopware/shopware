<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\If_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * The unit suite runs with every registered feature flag active (the FeatureFlagExtension rewrites the
 * environment per test) and, since no kernel boots, with every flag a plugin registers through its bundle
 * configuration unknown and therefore inactive. A skip guard on a flag cannot vary there: it skips on every
 * run or on none, and the same holds for a `markTestSkipped()` in any branch of an `if` chain whose
 * conditions read `Feature::isActive()`, the `else` and `elseif` branches included. The legacy branch of a
 * flag is exercised with `#[DisabledFeatures]` instead.
 *
 * Enforcement is narrowed to the namespaces the extension rewrites ({@see Configuration}); suites whose flag
 * state comes from the job environment keep their guards.
 *
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class NoFeatureSkipInUnitTestsRule implements Rule
{
    public const ERROR_SKIP_GUARD = 'Feature::%s() cannot vary in the unit suite: every flag registered there is active and any other flag inactive on every run, so this guard skips forever or never. Put #[DisabledFeatures([...])] on the test or the class to run the legacy branch, or drop the guard.';

    public const ERROR_IS_ACTIVE_GUARD = 'markTestSkipped() behind Feature::isActive() cannot vary in the unit suite: every flag registered there is active and any other flag inactive on every run, so this guard skips forever or never. Put #[DisabledFeatures([...])] on the test or the class to run the legacy branch, or drop the guard.';

    private const SKIP_METHODS = ['skipTestIfActive', 'skipTestIfInActive'];

    /**
     * @var list<string>
     */
    private readonly array $enabledNamespaces;

    public function __construct(Configuration $configuration)
    {
        $this->enabledNamespaces = $configuration->getFeatureSkipInUnitTestsEnabledNamespaces();
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();
        if (!TestRuleHelper::isTestClass($classReflection) || !$this->isEnabledNamespace($classReflection->getName())) {
            return [];
        }

        $errors = [];
        $finder = new NodeFinder();
        foreach ($finder->findInstanceOf($node->getOriginalNode(), StaticCall::class) as $call) {
            $method = $this->featureMethod($call, $scope);
            if ($method === null || !\in_array($method, self::SKIP_METHODS, true)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(self::ERROR_SKIP_GUARD, $method))
                ->identifier('shopware.featureSkipInUnitTest')
                ->line($call->getStartLine())
                ->build();
        }

        foreach ($finder->findInstanceOf($node->getOriginalNode(), If_::class) as $if) {
            // the whole chain: a skip in an else or elseif branch is as frozen as one in the if body
            $conditions = [$if->cond, ...array_map(static fn (ElseIf_ $elseIf): Node => $elseIf->cond, $if->elseifs)];
            $statements = array_merge($if->stmts, ...array_map(static fn (ElseIf_ $elseIf): array => $elseIf->stmts, $if->elseifs));
            $statements = array_merge($statements, $if->else->stmts ?? []);

            if (!$this->mentionsIsActive($conditions, $scope) || !$this->skips($statements)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(self::ERROR_IS_ACTIVE_GUARD)
                ->identifier('shopware.featureSkipInUnitTest')
                ->line($if->getStartLine())
                ->build();
        }

        return $errors;
    }

    /**
     * The method name when the call is a static call on {@see Feature}, null otherwise.
     */
    private function featureMethod(StaticCall $call, Scope $scope): ?string
    {
        if (!$call->class instanceof Name || !$call->name instanceof Identifier) {
            return null;
        }

        if ($scope->resolveName($call->class) !== Feature::class) {
            return null;
        }

        return $call->name->name;
    }

    /**
     * @param array<Node> $conditions
     */
    private function mentionsIsActive(array $conditions, Scope $scope): bool
    {
        foreach ($conditions as $condition) {
            if ($condition instanceof StaticCall && $this->featureMethod($condition, $scope) === 'isActive') {
                return true;
            }

            foreach ((new NodeFinder())->findInstanceOf($condition, StaticCall::class) as $call) {
                if ($this->featureMethod($call, $scope) === 'isActive') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<Node> $statements
     */
    private function skips(array $statements): bool
    {
        foreach ((new NodeFinder())->find($statements, static fn (Node $node): bool => $node instanceof MethodCall || $node instanceof StaticCall) as $call) {
            \assert($call instanceof MethodCall || $call instanceof StaticCall);
            if ($call->name instanceof Identifier && $call->name->name === 'markTestSkipped') {
                return true;
            }
        }

        return false;
    }

    private function isEnabledNamespace(string $className): bool
    {
        foreach ($this->enabledNamespaces as $namespace) {
            if (\str_contains($className, $namespace)) {
                return true;
            }
        }

        return false;
    }
}
