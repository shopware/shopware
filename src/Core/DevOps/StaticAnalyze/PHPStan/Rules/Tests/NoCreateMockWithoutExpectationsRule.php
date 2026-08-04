<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
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
 * Only flags what it can prove, to never block CI on a legitimate mock: a double handed to a `$this->`/
 * `self::`/`static::` call is skipped unless the callee is a method of the same class whose matching parameter
 * provably never reaches an `->expects()` (see {@see self::parameterIsNeverExpected()}) — that covers the
 * ubiquitous `createController(dep: $double)` fixture helper, which only forwards into the SUT constructor.
 * Properties touched by a helper are still skipped wholesale. The reverse — converting a real mock — is caught
 * for free by phpstan-phpunit (`Stub::expects()` is undefined).
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
     * The domain-by-domain rollout is complete: every unit test suite is covered.
     *
     * @var list<string>
     */
    private const ENABLED_NAMESPACES = [
        'Shopware\\Tests\\Unit\\Core\\',
        'Shopware\\Tests\\Unit\\Administration\\',
        'Shopware\\Tests\\Unit\\Storefront\\',
        'Shopware\\Tests\\Unit\\Elasticsearch\\',
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
        $ownMethods = $this->indexByName($methods);

        $errors = [];
        foreach ($methods as $method) {
            if ($method->stmts === null) {
                continue;
            }

            foreach ($this->findLocalStubMocks($method->stmts, $ownMethods) as $assign) {
                $errors[] = $this->buildError($assign->expr, $assign->getStartLine(), self::ERROR_STUB);
            }

            foreach ($this->findInlineStubMocks($method->stmts, $ownMethods) as $call) {
                $errors[] = $this->buildError($call, $call->getStartLine(), self::ERROR_STUB);
            }
        }

        foreach ($this->findPropertyMockIssues($methods, $ownMethods) as [$assign, $message]) {
            $errors[] = $this->buildError($assign->expr, $assign->getStartLine(), $message);
        }

        return $errors;
    }

    /**
     * Inline `createMock(X)` not assigned to a variable/property — e.g. passed straight into the SUT. It can
     * never carry an `->expects()` (there is nothing to call it on later), so unless it is immediately
     * `createMock(X)->expects(...)` it is a pure stub. Passing it into an unresolvable `$this->`/`self::`/
     * `static::` call is the one escape hatch (a helper could configure it), so those are skipped.
     *
     * @param array<Node> $stmts statements of a single method
     * @param array<string, ClassMethod> $ownMethods
     *
     * @return list<MethodCall|StaticCall>
     */
    private function findInlineStubMocks(array $stmts, array $ownMethods): array
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
        // createMock(X) passed into an opaque `$this->`/`self::`/`static::` call → expectations could be set out of view
        $this->eachOpaqueOwnCallArg($finder, $stmts, $ownMethods, function (Arg $arg) use (&$skip): void {
            if ($this->isCreateMockCall($arg->value)) {
                $skip[spl_object_id($arg->value)] = true;
            }
        });
        // handed back to the caller → it can be ->expects()-ed there
        foreach ($finder->findInstanceOf($stmts, Return_::class) as $return) {
            foreach ($finder->findInstanceOf([$return], MethodCall::class) as $inner) {
                $skip[spl_object_id($inner)] = true;
            }
            foreach ($finder->findInstanceOf([$return], StaticCall::class) as $inner) {
                $skip[spl_object_id($inner)] = true;
            }
        }

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
     * @param array<string, ClassMethod> $ownMethods
     *
     * @return list<Assign>
     */
    private function findLocalStubMocks(array $stmts, array $ownMethods): array
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
        $this->disqualifyDoublesPassedToOwnMethods($finder, $stmts, $ownMethods, $disqualified, fn (Node $n): ?string => $this->localName($n));

        // handed back to the caller (a `getFooMock()` fixture helper) → it can be ->expects()-ed there
        foreach ($this->findReturnedVariableNames($finder, $stmts) as $name) {
            $disqualified[$name] = true;
        }

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
     * @param array<string, ClassMethod> $ownMethods
     *
     * @return list<array{Assign, string}>
     */
    private function findPropertyMockIssues(array $methods, array $ownMethods): array
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
            if ($this->isPropertyOffLimits($finder, $methods, $helperMethods, $ownMethods, $name)) {
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
     * @param array<string, ClassMethod> $ownMethods
     */
    private function isPropertyOffLimits(NodeFinder $finder, array $methods, array $helperMethods, array $ownMethods, string $name): bool
    {
        $passed = [];
        foreach ($methods as $method) {
            $this->disqualifyDoublesPassedToOwnMethods($finder, (array) $method->stmts, $ownMethods, $passed, fn (Node $n): ?string => $this->propertyName($n));
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
     * @param array<string, ClassMethod> $ownMethods
     * @param array<string, true> $disqualified
     * @param callable(Node): ?string $resolveName
     */
    private function disqualifyDoublesPassedToOwnMethods(NodeFinder $finder, array $stmts, array $ownMethods, array &$disqualified, callable $resolveName): void
    {
        $this->eachOpaqueOwnCallArg($finder, $stmts, $ownMethods, function (Arg $arg) use (&$disqualified, $resolveName): void {
            $name = $resolveName($arg->value);
            if ($name !== null) {
                $disqualified[$name] = true;
            }
        });
    }

    /**
     * Invokes $onArg for every argument of a `$this->`/`self::`/`static::` call in $stmts that could carry an
     * expectation configured out of view. Arguments landing on a parameter of a method of this same class that
     * provably never reaches an `->expects()` are not reported: nothing is hidden there.
     *
     * @param array<Node> $stmts
     * @param array<string, ClassMethod> $ownMethods
     * @param callable(Arg): void $onArg
     */
    private function eachOpaqueOwnCallArg(NodeFinder $finder, array $stmts, array $ownMethods, callable $onArg): void
    {
        if ($this->hasOpaqueExpectsReceiver($finder, $stmts)) {
            // Some double is ->expects()-ed through a reference this rule cannot resolve (typically a fixture
            // struct handed back by the helper: `$fixture->repository->expects(...)`). Which double that is
            // cannot be told, so no callee counts as transparent here.
            $ownMethods = [];
        }

        foreach ($this->findOwnCalls($finder, $stmts) as $call) {
            $callee = $this->resolveOwnMethod($call, $ownMethods);

            foreach ($call->getArgs() as $index => $arg) {
                if ($callee !== null && $this->argumentIsNeverExpected($callee, $arg, $index, $ownMethods, [])) {
                    continue;
                }

                $onArg($arg);
            }
        }
    }

    /**
     * The test's own calls — `$this->x()`, `self::x()`, `static::x()` — the only place an expectation can be
     * configured on a double out of view.
     *
     * @param array<Node> $stmts
     *
     * @return list<MethodCall|StaticCall>
     */
    private function findOwnCalls(NodeFinder $finder, array $stmts): array
    {
        $calls = [];
        foreach ($finder->findInstanceOf($stmts, MethodCall::class) as $call) {
            if ($call->var instanceof Variable && $call->var->name === 'this' && !$call->isFirstClassCallable()) {
                $calls[] = $call;
            }
        }

        foreach ($finder->findInstanceOf($stmts, StaticCall::class) as $call) {
            if ($call->class instanceof Name && \in_array(mb_strtolower($call->class->toString()), ['self', 'static'], true) && !$call->isFirstClassCallable()) {
                $calls[] = $call;
            }
        }

        return $calls;
    }

    /**
     * Names of the local variables that leave $stmts through a `return` — directly or as part of whatever is
     * returned. Their doubles reach a caller this analysis does not follow, so nothing can be proven about them.
     *
     * @param array<Node> $stmts
     *
     * @return list<string>
     */
    private function findReturnedVariableNames(NodeFinder $finder, array $stmts): array
    {
        $names = [];
        foreach ($finder->findInstanceOf($stmts, Return_::class) as $return) {
            if ($return->expr === null) {
                continue;
            }

            foreach ($finder->findInstanceOf([$return->expr], Variable::class) as $variable) {
                $name = $this->localName($variable);
                if ($name !== null) {
                    $names[$name] = true;
                }
            }
        }

        return array_keys($names);
    }

    /**
     * True when $stmts configures an expectation on something this rule cannot map back to a double — anything
     * other than a local variable or a `$this->` property, e.g. `$fixture->repository->expects(...)`.
     *
     * @param array<Node> $stmts
     */
    private function hasOpaqueExpectsReceiver(NodeFinder $finder, array $stmts): bool
    {
        foreach ($finder->findInstanceOf($stmts, MethodCall::class) as $call) {
            if (!$this->isExpectsCall($call)) {
                continue;
            }

            if ($this->localName($call->var) === null && $this->propertyName($call->var) === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, ClassMethod> $ownMethods
     */
    private function resolveOwnMethod(MethodCall|StaticCall $call, array $ownMethods): ?ClassMethod
    {
        if (!$call->name instanceof Identifier) {
            return null;
        }

        return $ownMethods[mb_strtolower($call->name->name)] ?? null;
    }

    /**
     * Resolves which parameter of $callee receives $arg (positionally or by name) and answers whether a double
     * bound to it can still end up `->expects()`-ed. Anything not understood answers false, keeping the double
     * skipped.
     *
     * @param array<string, ClassMethod> $ownMethods
     * @param array<string, true> $visited guards against recursive helpers
     */
    private function argumentIsNeverExpected(ClassMethod $callee, Arg $arg, int $index, array $ownMethods, array $visited): bool
    {
        if ($arg->unpack || $callee->stmts === null) {
            return false;
        }

        $param = null;
        if ($arg->name instanceof Identifier) {
            foreach ($callee->params as $candidate) {
                if ($candidate->var instanceof Variable && $candidate->var->name === $arg->name->name) {
                    $param = $candidate;
                    break;
                }
            }
        } else {
            $param = $callee->params[$index] ?? null;
        }

        if ($param === null || $param->variadic || $param->byRef || !$param->var instanceof Variable || !\is_string($param->var->name)) {
            return false;
        }

        $key = mb_strtolower($callee->name->name);
        if (isset($visited[$key])) {
            return false;
        }
        $visited[$key] = true;

        return $this->parameterIsNeverExpected($callee, $param->var->name, $ownMethods, $visited);
    }

    /**
     * True when every single use of $paramName inside $method is provably harmless: receiving a non-`expects()`
     * call (stub configuration such as `->method()->willReturn()`), being handed to a constructor — the fixture
     * helper forwarding a double into the SUT — or being forwarded to another own method that is itself
     * harmless. Any other use (assignment, `return`, a call this rule cannot resolve) answers false.
     *
     * @param array<string, ClassMethod> $ownMethods
     * @param array<string, true> $visited
     */
    private function parameterIsNeverExpected(ClassMethod $method, string $paramName, array $ownMethods, array $visited): bool
    {
        $finder = new NodeFinder();
        $stmts = (array) $method->stmts;

        $uses = [];
        foreach ($finder->findInstanceOf($stmts, Variable::class) as $variable) {
            if ($variable->name === $paramName) {
                $uses[spl_object_id($variable)] = true;
            }
        }

        if ($uses === []) {
            return true;
        }

        $harmless = [];

        // `$param->method(...)` — stub configuration is fine, `$param->expects(...)` is not, and a dynamic
        // method name could be either.
        foreach ($finder->findInstanceOf($stmts, MethodCall::class) as $call) {
            if (!$call->var instanceof Variable || $call->var->name !== $paramName) {
                continue;
            }

            if (!$call->name instanceof Identifier || $this->isExpectsCall($call)) {
                return false;
            }

            $harmless[spl_object_id($call->var)] = true;
        }

        // `new Sut($param)` — production code, it cannot configure expectations.
        foreach ($finder->findInstanceOf($stmts, New_::class) as $new) {
            if ($new->isFirstClassCallable()) {
                continue;
            }

            foreach ($new->getArgs() as $arg) {
                foreach ($this->passedThroughVariables($arg->value, $paramName) as $variable) {
                    $harmless[spl_object_id($variable)] = true;
                }
            }
        }

        // forwarded to another own method → recurse into it
        foreach ($this->findOwnCalls($finder, $stmts) as $call) {
            $callee = $this->resolveOwnMethod($call, $ownMethods);
            if ($callee === null) {
                continue;
            }

            foreach ($call->getArgs() as $index => $arg) {
                $variables = $this->passedThroughVariables($arg->value, $paramName);
                if ($variables === [] || !$this->argumentIsNeverExpected($callee, $arg, $index, $ownMethods, $visited)) {
                    continue;
                }

                foreach ($variables as $variable) {
                    $harmless[spl_object_id($variable)] = true;
                }
            }
        }

        foreach (array_keys($uses) as $id) {
            if (!isset($harmless[$id])) {
                return false;
            }
        }

        return true;
    }

    /**
     * The $paramName occurrences that $expr passes on unchanged — the bare variable, or one behind the
     * defaulting wrappers fixture helpers use (`$param ?? $this->shared`, `$param ?: $fallback`). Occurrences
     * anywhere else in $expr are not passed on unchanged and are left for the caller to reject.
     *
     * @return list<Variable>
     */
    private function passedThroughVariables(Expr $expr, string $paramName): array
    {
        if ($expr instanceof Variable) {
            return $expr->name === $paramName ? [$expr] : [];
        }

        if ($expr instanceof Coalesce) {
            return [...$this->passedThroughVariables($expr->left, $paramName), ...$this->passedThroughVariables($expr->right, $paramName)];
        }

        if ($expr instanceof Ternary) {
            return [
                ...$this->passedThroughVariables($expr->if ?? $expr->cond, $paramName),
                ...$this->passedThroughVariables($expr->else, $paramName),
            ];
        }

        return [];
    }

    /**
     * @param array<ClassMethod> $methods
     *
     * @return array<string, ClassMethod>
     */
    private function indexByName(array $methods): array
    {
        $indexed = [];
        foreach ($methods as $method) {
            $indexed[mb_strtolower($method->name->name)] = $method;
        }

        return $indexed;
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
            && !$expr->isFirstClassCallable()
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
