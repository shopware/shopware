<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * Static guard for the PHPUnit 12+ "no expectations configured for mock … use a test stub" notice: flags a
 * `createMock()` double that is never `->expects()`-ed (local var, inline argument, or property) and points it
 * to `createStub()`. A property `->expects()`-ed in only some tests is flagged as mixed usage instead (fix it
 * per-method, not with `createStub()`).
 *
 * Only flags what it can prove, to never block CI on a legitimate mock: doubles passed into a `$this->`/
 * `self::`/`static::` call (or properties touched by a helper) are skipped. The reverse — converting a real
 * mock — is caught for free by phpstan-phpunit (`Stub::expects()` is undefined).
 *
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class NoCreateMockWithoutExpectationsRule implements Rule
{
    public const ERROR_STUB = 'createMock(%s) is only used as a stub (no ->expects() is configured on it). Use createStub(%s) instead, the correct PHPUnit API for a test double without call expectations.';

    public const ERROR_MIXED = 'createMock(%s) is a shared mock that is ->expects()-ed in some test methods but left without an expectation in others, so it triggers the PHPUnit "no expectations" notice there. Do not mix mock and stub usage on one shared double: give it a real expectation (e.g. ->expects($this->never())) in every test, split the test, or use a per-test double.';

    /**
     * The rule is rolled out domain by domain for early adoption
     *
     * @var list<string>
     */
    private const ENABLED_NAMESPACES = [
        'Shopware\\Tests\\Unit\\Core\\DevOps\\',
        'Shopware\\Tests\\Unit\\Core\\Profiling\\',
    ];

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
        if (!TestRuleHelper::isUnitTestClass($classReflection) || !$this->isEnabledNamespace($classReflection->getName())) {
            return [];
        }

        $class = $node->getOriginalNode();
        $methods = $class->getMethods();

        $errors = [];
        foreach ($methods as $method) {
            if ($method->stmts === null) {
                continue;
            }

            foreach ($this->findLocalStubMocks($method->stmts) as $assign) {
                $errors[] = $this->buildError($assign->expr, $assign->getStartLine(), self::ERROR_STUB);
            }

            foreach ($this->findInlineStubMocks($method->stmts) as $call) {
                $errors[] = $this->buildError($call, $call->getStartLine(), self::ERROR_STUB);
            }
        }

        foreach ($this->findPropertyMockIssues($methods) as [$assign, $message]) {
            $errors[] = $this->buildError($assign->expr, $assign->getStartLine(), $message);
        }

        return $errors;
    }

    /**
     * Inline `createMock(X)` not assigned to a variable/property — e.g. passed straight into the SUT. It can
     * never carry an `->expects()` (there is nothing to call it on later), so unless it is immediately
     * `createMock(X)->expects(...)` it is a pure stub. Passing it into a `$this->`/`self::`/`static::` call is
     * the one escape hatch (a helper could configure it), so those are skipped.
     *
     * @param array<Node> $stmts statements of a single method
     *
     * @return list<MethodCall|StaticCall>
     */
    private function findInlineStubMocks(array $stmts): array
    {
        $finder = new NodeFinder();
        $calls = [...$finder->findInstanceOf($stmts, MethodCall::class), ...$finder->findInstanceOf($stmts, StaticCall::class)];

        $skip = [];
        // assigned createMock → handled by the local/property analysis
        foreach ($finder->findInstanceOf($stmts, Assign::class) as $assign) {
            if ($this->isCreateMockCall($assign->expr)) {
                $skip[spl_object_id($assign->expr)] = true;
            }
        }
        // `createMock(X)->expects(...)` → real mock
        foreach ($calls as $call) {
            if ($call instanceof MethodCall && $this->isExpectsCall($call) && $this->isCreateMockCall($call->var)) {
                $skip[spl_object_id($call->var)] = true;
            }
        }
        // createMock(X) passed into a `$this->`/`self::`/`static::` call → expectations could be set out of view
        $this->eachOwnCallArg($finder, $stmts, function (Arg $arg) use (&$skip): void {
            if ($this->isCreateMockCall($arg->value)) {
                $skip[spl_object_id($arg->value)] = true;
            }
        });

        $result = [];
        foreach ($calls as $call) {
            if ($this->isCreateMockCall($call) && !isset($skip[spl_object_id($call)])) {
                $result[] = $call;
            }
        }

        return $result;
    }

    /**
     * @param array<Node> $stmts statements of a single method
     *
     * @return list<Assign>
     */
    private function findLocalStubMocks(array $stmts): array
    {
        $finder = new NodeFinder();

        /** @var array<string, Assign> $assignments */
        $assignments = [];
        foreach ($finder->findInstanceOf($stmts, Assign::class) as $assign) {
            if ($assign->var instanceof Variable && \is_string($assign->var->name) && $this->isCreateMockCall($assign->expr)) {
                $assignments[$assign->var->name] = $assign;
            }
        }

        if ($assignments === []) {
            return [];
        }

        $disqualified = [];
        foreach ($finder->findInstanceOf($stmts, MethodCall::class) as $call) {
            if ($this->isExpectsCall($call)) {
                $name = $this->localName($call->var);
                if ($name !== null) {
                    $disqualified[$name] = true;
                }
            }
        }
        $this->disqualifyDoublesPassedToOwnMethods($finder, $stmts, $disqualified, fn (Node $n): ?string => $this->localName($n));

        $result = [];
        foreach ($assignments as $name => $assign) {
            if (!isset($disqualified[$name])) {
                $result[] = $assign;
            }
        }

        return $result;
    }

    /**
     * @param array<ClassMethod> $methods
     *
     * @return array<string, list<Assign>> createMock property assignments, keyed by property name
     */
    private function collectPropertyMockAssignments(NodeFinder $finder, array $methods): array
    {
        $assignments = [];
        foreach ($methods as $method) {
            foreach ($finder->findInstanceOf((array) $method->stmts, Assign::class) as $assign) {
                $name = $this->propertyName($assign->var);
                if ($name !== null && $this->isCreateMockCall($assign->expr)) {
                    $assignments[$name][] = $assign;
                }
            }
        }

        return $assignments;
    }

    /**
     * @param array<ClassMethod> $methods
     *
     * @return list<array{Assign, string}>
     */
    private function findPropertyMockIssues(array $methods): array
    {
        $finder = new NodeFinder();

        $assignments = $this->collectPropertyMockAssignments($finder, $methods);
        if ($assignments === []) {
            return [];
        }

        $setUp = $this->findSetUp($methods);
        $testMethods = array_filter($methods, fn (ClassMethod $m): bool => $this->isTestMethod($m));
        $helperMethods = array_filter(
            $methods,
            fn (ClassMethod $m): bool => !$this->isTestMethod($m) && mb_strtolower($m->name->name) !== 'setup'
        );

        $errors = [];
        foreach ($assignments as $name => $propAssignments) {
            if ($this->isPropertyOffLimits($finder, $methods, $helperMethods, $name)) {
                continue;
            }

            $expectsInSetUp = $setUp !== null && $this->methodExpectsProperty($finder, $setUp, $name);
            $createdInSetUp = $setUp !== null && $this->methodCreatesProperty($finder, $setUp, $name);

            $noticing = false;
            $hasExpects = $expectsInSetUp;
            foreach ($testMethods as $test) {
                // For a setUp-created (shared) mock every test owns an instance; otherwise only the tests
                // that create it themselves are relevant.
                if (!$createdInSetUp && !$this->methodCreatesProperty($finder, $test, $name)) {
                    continue;
                }

                if ($this->methodExpectsProperty($finder, $test, $name)) {
                    $hasExpects = true;

                    continue;
                }

                if (!$expectsInSetUp) {
                    $noticing = true;
                }
            }

            if (!$noticing) {
                continue;
            }

            $message = $hasExpects ? self::ERROR_MIXED : self::ERROR_STUB;
            foreach ($propAssignments as $assign) {
                $errors[] = [$assign, $message];
            }
        }

        return $errors;
    }

    /**
     * A property whose expectations could be configured out of view — passed into a `$this->`/`self::`/
     * `static::` call, or accessed by a non-test/non-setUp helper — cannot be reasoned about safely.
     *
     * @param array<ClassMethod> $methods
     * @param array<ClassMethod> $helperMethods
     */
    private function isPropertyOffLimits(NodeFinder $finder, array $methods, array $helperMethods, string $name): bool
    {
        $passed = [];
        foreach ($methods as $method) {
            $this->disqualifyDoublesPassedToOwnMethods($finder, (array) $method->stmts, $passed, fn (Node $n): ?string => $this->propertyName($n));
        }
        if (isset($passed[$name])) {
            return true;
        }

        foreach ($helperMethods as $helper) {
            foreach ($finder->findInstanceOf((array) $helper->stmts, PropertyFetch::class) as $fetch) {
                if ($this->propertyName($fetch) === $name) {
                    return true;
                }
            }
        }

        return false;
    }

    private function methodExpectsProperty(NodeFinder $finder, ClassMethod $method, string $name): bool
    {
        foreach ($finder->findInstanceOf((array) $method->stmts, MethodCall::class) as $call) {
            if ($this->isExpectsCall($call) && $this->propertyName($call->var) === $name) {
                return true;
            }
        }

        return false;
    }

    private function methodCreatesProperty(NodeFinder $finder, ClassMethod $method, string $name): bool
    {
        foreach ($finder->findInstanceOf((array) $method->stmts, Assign::class) as $assign) {
            if ($this->propertyName($assign->var) === $name && $this->isCreateMockCall($assign->expr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<Node> $stmts
     * @param array<string, true> $disqualified
     * @param callable(Node): ?string $resolveName
     */
    private function disqualifyDoublesPassedToOwnMethods(NodeFinder $finder, array $stmts, array &$disqualified, callable $resolveName): void
    {
        $this->eachOwnCallArg($finder, $stmts, function (Arg $arg) use (&$disqualified, $resolveName): void {
            $name = $resolveName($arg->value);
            if ($name !== null) {
                $disqualified[$name] = true;
            }
        });
    }

    /**
     * Invokes $onArg for every argument of a `$this->`/`self::`/`static::` call in $stmts — the one place that
     * defines "the test's own call", where an expectation could be configured on a double out of view.
     *
     * @param array<Node> $stmts
     * @param callable(Arg): void $onArg
     */
    private function eachOwnCallArg(NodeFinder $finder, array $stmts, callable $onArg): void
    {
        foreach ($finder->findInstanceOf($stmts, MethodCall::class) as $call) {
            if ($call->var instanceof Variable && $call->var->name === 'this') {
                foreach ($call->getArgs() as $arg) {
                    $onArg($arg);
                }
            }
        }

        foreach ($finder->findInstanceOf($stmts, StaticCall::class) as $call) {
            if ($call->class instanceof Name && \in_array(mb_strtolower($call->class->toString()), ['self', 'static'], true)) {
                foreach ($call->getArgs() as $arg) {
                    $onArg($arg);
                }
            }
        }
    }

    private function isTestMethod(ClassMethod $method): bool
    {
        if (!$method->isPublic() || $method->stmts === null) {
            return false;
        }

        if (mb_stripos($method->name->name, 'test') === 0) {
            return true;
        }

        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if (\str_ends_with($attr->name->toString(), 'Test')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<ClassMethod> $methods
     */
    private function findSetUp(array $methods): ?ClassMethod
    {
        foreach ($methods as $method) {
            if (mb_strtolower($method->name->name) === 'setup') {
                return $method;
            }
        }

        return null;
    }

    private function isExpectsCall(MethodCall $call): bool
    {
        return $call->name instanceof Identifier && $call->name->name === 'expects';
    }

    private function localName(Node $node): ?string
    {
        return $node instanceof Variable && \is_string($node->name) ? $node->name : null;
    }

    private function propertyName(Node $node): ?string
    {
        if ($node instanceof PropertyFetch
            && $node->var instanceof Variable
            && $node->var->name === 'this'
            && $node->name instanceof Identifier
        ) {
            return $node->name->name;
        }

        return null;
    }

    private function buildError(Node $createMockCall, int $line, string $message): RuleError
    {
        $label = $this->resolveMockedClass($createMockCall) ?? '...';

        return RuleErrorBuilder::message(\sprintf($message, $label, $label))
            ->identifier('shopware.createMockWithoutExpectations')
            ->line($line)
            ->build();
    }

    private function isEnabledNamespace(string $className): bool
    {
        foreach (self::ENABLED_NAMESPACES as $namespace) {
            if (\str_contains($className, $namespace)) {
                return true;
            }
        }

        return false;
    }

    private function isCreateMockCall(Node $expr): bool
    {
        return ($expr instanceof MethodCall || $expr instanceof StaticCall)
            && $expr->name instanceof Identifier
            && $expr->name->name === 'createMock'
            && \count($expr->getArgs()) === 1;
    }

    private function resolveMockedClass(Node $expr): ?string
    {
        if (!$expr instanceof MethodCall && !$expr instanceof StaticCall) {
            return null;
        }

        $arg = $expr->getArgs()[0]->value ?? null;
        if ($arg instanceof ClassConstFetch && $arg->class instanceof Name) {
            return $arg->class->getLast() . '::class';
        }

        return null;
    }
}
