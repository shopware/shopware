<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp\Coalesce as CoalesceAssign;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Parser\Parser;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\Framework\Log\Package;

/**
 * Static guard for the PHPUnit 12+ "no expectations configured for mock … use a test stub" notice: flags a
 * `createMock()` double that will trigger it, with the fix stated in the message ({@see self::ERROR_STUB},
 * {@see self::ERROR_MIXED}, {@see self::ERROR_ORPHANED}). It only flags what it can prove; anything it cannot
 * resolve is skipped.
 *
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class NoCreateMockWithoutExpectationsRule implements Rule
{
    public const ERROR_STUB = 'createMock(%s) is only used as a stub (no ->expects() is configured on it). Use createStub(%s) instead, the correct PHPUnit API for a test double without call expectations.';

    public const ERROR_MIXED = 'createMock(%s) is a shared mock that is ->expects()-ed in some test methods but left without an expectation in %s, so it triggers the PHPUnit "no expectations" notice there. Do not mix mock and stub usage on one shared double: give it a real expectation (e.g. ->expects($this->never())) in every test, split the test, or use a per-test double.';

    public const ERROR_ORPHANED = 'createMock(%s) is created in setUp() and re-created via `$this->... = $this->createMock(...)` in %s. Re-assigning the property replaces this instance before it is used, so it never receives an expectation and triggers the PHPUnit "no expectations" notice. Configure the setUp instance directly in those tests instead of re-creating it (or move the creation out of setUp).';

    /**
     * ClassMethod attribute carrying the ancestor file an inherited method was parsed from.
     */
    private const SOURCE_FILE = 'noCreateMockRuleSourceFile';

    /**
     * ClassMethod attribute carrying the hierarchy depth a method was declared at: 0 for the analysed
     * class itself, 1 for its nearest test-class ancestor, and so on. `parent::x()` resolves to the
     * nearest declaration of x strictly above the calling method's depth.
     */
    private const DEPTH = 'noCreateMockRuleDepth';

    /**
     * ClassMethod attribute marking a shadowed ancestor method that joined the analysis because a
     * `parent::x()` call chains into it. Such a method has no direct call sites of its own.
     */
    private const CHAINED = 'noCreateMockRuleChained';

    /**
     * StaticCall attribute carrying the method-map key ("name@depth") a `parent::x()` call resolves to.
     */
    private const PARENT_TARGET = 'noCreateMockRuleParentTarget';

    /**
     * Methods of PHPUnit's double-configuration chain whose arguments are consumed as data (method names,
     * matchers, return values) — they can never configure an expectation on a double passed into them.
     *
     * @var list<string>
     */
    private const DOUBLE_CONFIGURATION_METHODS = [
        'method',
        'with',
        'willReturn',
        'willReturnMap',
        'willReturnOnConsecutiveCalls',
        'willReturnArgument',
        'willThrowException',
    ];

    /**
     * Narrows enforcement to matching test namespaces; an empty list disables the rule.
     * Consumers rolling the rule out domain by domain grow this list via the
     * `shopware.createMockWithoutExpectationsEnabledNamespaces` parameter of their PHPStan config.
     *
     * @var list<string>
     */
    private readonly array $enabledNamespaces;

    public function __construct(
        Configuration $configuration,
        private readonly Parser $parser,
    ) {
        $this->enabledNamespaces = $configuration->getCreateMockWithoutExpectationsEnabledNamespaces();
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

        // the notice fires per concrete class run; the subclasses analyse the inherited fixtures with all
        // call sites in view
        if ($classReflection->isAbstract()) {
            return [];
        }

        $class = $node->getOriginalNode();
        $chains = $this->ancestorMethodChains($classReflection);
        $own = $this->indexByName($class->getMethods());
        foreach ($own as $method) {
            $method->setAttribute(self::DEPTH, 0);
        }

        $nearestWins = array_map(static fn (array $chain): ClassMethod => $chain[0], $chains);
        $ownMethods = array_merge($nearestWins, $own);
        $this->linkParentCalls($ownMethods, $chains);
        $methods = array_values($ownMethods);

        $errors = [];
        foreach ($methods as $method) {
            if ($method->stmts === null) {
                continue;
            }

            $file = $this->sourceFile($method);
            foreach ($this->findLocalStubMocks($method->stmts, $ownMethods) as $assign) {
                $errors[] = $this->buildError($assign->expr, $assign->getStartLine(), self::ERROR_STUB, null, $file);
            }

            foreach ($this->findInlineStubMocks($method->stmts, $ownMethods) as $call) {
                $errors[] = $this->buildError($call, $call->getStartLine(), self::ERROR_STUB, null, $file);
            }
        }

        foreach ($this->findPropertyMockIssues($methods, $ownMethods) as [$assign, $message, $detail, $file]) {
            $errors[] = $this->buildError($assign->expr, $assign->getStartLine(), $message, $detail, $file);
        }

        foreach ($this->findHelperReturnedStubMocks($methods, $ownMethods) as [$createMock, $file]) {
            $errors[] = $this->buildError($createMock, $createMock->getStartLine(), self::ERROR_STUB, null, $file);
        }

        return $errors;
    }

    /**
     * Every declaration of every method this class inherits from its test-class ancestors, parsed from the
     * ancestor files: keyed by lowercased name, each chain ordered nearest ancestor first. Each declaration
     * carries its origin in the {@see self::SOURCE_FILE} attribute for error reporting and its hierarchy
     * depth in {@see self::DEPTH} so `parent::x()` calls can resolve to the declaration they chain into.
     * Ancestors outside the enabled namespaces (the framework's TestCase and other vendor bases)
     * contribute nothing.
     *
     * @return array<string, non-empty-list<ClassMethod>>
     */
    private function ancestorMethodChains(ClassReflection $classReflection): array
    {
        $chains = [];
        $depth = 0;
        foreach ($classReflection->getParents() as $parent) {
            ++$depth;
            if (!TestRuleHelper::isTestClass($parent) || !$this->isEnabledNamespace($parent->getName())) {
                continue;
            }

            $file = $parent->getFileName();
            if ($file === null) {
                continue;
            }

            $classNode = $this->classNodeIn($file, $parent->getName());
            if ($classNode === null) {
                continue;
            }

            foreach ($classNode->getMethods() as $method) {
                $method->setAttribute(self::SOURCE_FILE, $file);
                $method->setAttribute(self::DEPTH, $depth);
                $chains[mb_strtolower($method->name->name)][] = $method;
            }
        }

        return $chains;
    }

    /**
     * Follows `parent::x()` calls into the shadowed ancestor declarations of x. Without this, a subclass
     * `setUp()` that chains `parent::setUp()` would shadow the base method out of view together with the
     * `createMock()` fixtures it creates — the exact doubles that trigger the runtime notice in every
     * subclass. Each chained-into declaration joins $ownMethods under an "x@depth" key, the call node is
     * annotated with that key for {@see self::resolveOwnMethod()} and the call graph, and the declaration
     * is scanned for further `parent::` chaining of its own.
     *
     * @param array<string, ClassMethod> $ownMethods
     * @param array<string, non-empty-list<ClassMethod>> $chains
     */
    private function linkParentCalls(array &$ownMethods, array $chains): void
    {
        $finder = new NodeFinder();
        $queue = array_values($ownMethods);

        while ($queue !== []) {
            $method = array_shift($queue);
            $callerDepth = $method->getAttribute(self::DEPTH);
            if (!\is_int($callerDepth) || $method->stmts === null) {
                continue;
            }

            foreach ($finder->findInstanceOf($method->stmts, StaticCall::class) as $call) {
                if (!$call->class instanceof Name || mb_strtolower($call->class->toString()) !== 'parent' || !$call->name instanceof Identifier) {
                    continue;
                }

                $target = null;
                foreach ($chains[mb_strtolower($call->name->name)] ?? [] as $declaration) {
                    $declarationDepth = $declaration->getAttribute(self::DEPTH);
                    if (\is_int($declarationDepth) && $declarationDepth > $callerDepth) {
                        $target = $declaration;
                        break;
                    }
                }

                if ($target === null) {
                    continue;
                }

                $key = mb_strtolower($call->name->name) . '@' . $target->getAttribute(self::DEPTH);
                $call->setAttribute(self::PARENT_TARGET, $key);

                if (!isset($ownMethods[$key])) {
                    $target->setAttribute(self::CHAINED, true);
                    $ownMethods[$key] = $target;
                    $queue[] = $target;
                }
            }
        }
    }

    private function classNodeIn(string $file, string $className): ?Class_
    {
        try {
            $stmts = $this->parser->parseFile($file);
        } catch (\Throwable) {
            return null;
        }

        foreach ((new NodeFinder())->findInstanceOf($stmts, Class_::class) as $class) {
            if ($class->namespacedName !== null && $class->namespacedName->toString() === $className) {
                return $class;
            }
        }

        return null;
    }

    private function sourceFile(ClassMethod $method): ?string
    {
        $file = $method->getAttribute(self::SOURCE_FILE);

        return \is_string($file) ? $file : null;
    }

    /**
     * A `getFooMock()` helper whose returned double is provably never `->expects()`-ed: neither inside the
     * helper nor at any call site of it in this class. The plain local/inline analyses skip returned doubles
     * ("the caller could expects() it"); this pass follows the return to those callers and flags the double
     * when none of them does.
     *
     * @param array<ClassMethod> $methods
     * @param array<string, ClassMethod> $ownMethods
     *
     * @return list<array{MethodCall|StaticCall, ?string}> createMock call and the ancestor file it lives in
     */
    private function findHelperReturnedStubMocks(array $methods, array $ownMethods): array
    {
        $finder = new NodeFinder();
        $result = [];

        foreach ($methods as $helper) {
            if ($this->isTestMethod($helper) || mb_strtolower($helper->name->name) === 'setup' || $helper->stmts === null || $helper->getAttribute(self::CHAINED) === true) {
                continue;
            }

            $returned = $this->returnedCreateMocks($finder, $helper, $ownMethods);
            if ($returned === []) {
                continue;
            }

            if (!$this->helperResultIsNeverExpected($finder, $methods, $ownMethods, $helper->name->name)) {
                continue;
            }

            foreach ($returned as $createMock) {
                $result[] = [$createMock, $this->sourceFile($helper)];
            }
        }

        return $result;
    }

    /**
     * The `createMock()` calls whose double leaves $helper through a `return` while provably clean inside it:
     * either `return $this->createMock(X);` directly, or a local that is only stub-configured before a bare
     * `return $local;`. Doubles the helper itself `->expects()`-es or lets escape any other way answer nothing.
     *
     * @param array<string, ClassMethod> $ownMethods
     *
     * @return list<MethodCall|StaticCall>
     */
    private function returnedCreateMocks(NodeFinder $finder, ClassMethod $helper, array $ownMethods): array
    {
        $stmts = (array) $helper->stmts;
        $result = [];

        // bare `return $x;` statements, keyed by variable name
        $bareReturns = [];
        foreach ($finder->findInstanceOf($stmts, Return_::class) as $return) {
            if ($return->expr !== null && $this->isCreateMockCall($return->expr)) {
                \assert($return->expr instanceof MethodCall || $return->expr instanceof StaticCall);
                $result[] = $return->expr;

                continue;
            }

            if ($return->expr instanceof Variable && \is_string($return->expr->name)) {
                $bareReturns[$return->expr->name][] = spl_object_id($return->expr);
            }
        }

        foreach ($finder->findInstanceOf($stmts, Assign::class) as $assign) {
            if (!$assign->var instanceof Variable || !\is_string($assign->var->name) || !$this->isCreateMockCall($assign->expr)) {
                continue;
            }

            $name = $assign->var->name;
            if (!isset($bareReturns[$name])) {
                continue;
            }

            // Inside the helper the double must stay clean: only stub configuration, constructor forwarding,
            // proven own-method forwarding, its defining assignment, and the bare return itself.
            $extraHarmless = [spl_object_id($assign->var), ...$bareReturns[$name]];

            if ($this->variableIsNeverExpected($stmts, $name, $ownMethods, $extraHarmless)) {
                \assert($assign->expr instanceof MethodCall || $assign->expr instanceof StaticCall);
                $result[] = $assign->expr;
            }
        }

        return $result;
    }

    /**
     * True when every call site of `$this->$helperName()` in the class provably never `->expects()`-es the
     * returned double: bound to a local that stays clean, chained into stub configuration, used as a bare
     * statement, forwarded into a constructor, or forwarded into an own method whose parameter is proven.
     * Any call site this rule cannot classify answers false.
     *
     * @param array<ClassMethod> $methods
     * @param array<string, ClassMethod> $ownMethods
     */
    private function helperResultIsNeverExpected(NodeFinder $finder, array $methods, array $ownMethods, string $helperName): bool
    {
        foreach ($methods as $method) {
            if ($method->stmts === null || mb_strtolower($method->name->name) === mb_strtolower($helperName)) {
                continue;
            }

            $stmts = (array) $method->stmts;

            $sites = [];
            foreach ($this->findOwnCalls($finder, $stmts) as $call) {
                if ($call->name instanceof Identifier && mb_strtolower($call->name->name) === mb_strtolower($helperName)) {
                    $sites[spl_object_id($call)] = $call;
                }
            }

            if ($sites === []) {
                continue;
            }

            $classified = [];

            // `$x = $this->helper();` — analyse $x like a local double.
            foreach ($finder->findInstanceOf($stmts, Assign::class) as $assign) {
                if (!isset($sites[spl_object_id($assign->expr)])) {
                    continue;
                }

                if (!$assign->var instanceof Variable || !\is_string($assign->var->name)) {
                    return false;
                }

                if (!$this->variableIsNeverExpected($stmts, $assign->var->name, $ownMethods, [spl_object_id($assign->var)])) {
                    return false;
                }

                $classified[spl_object_id($assign->expr)] = true;
            }

            // `$this->helper()->method(...)` — chained stub configuration; a chained `expects()` is a mock.
            foreach ($finder->findInstanceOf($stmts, MethodCall::class) as $call) {
                if (!isset($sites[spl_object_id($call->var)])) {
                    continue;
                }

                if (!$call->name instanceof Identifier || $this->isExpectsCall($call)) {
                    return false;
                }

                $classified[spl_object_id($call->var)] = true;
            }

            // `new Sut($this->helper())` — production code, it cannot configure expectations.
            foreach ($finder->findInstanceOf($stmts, New_::class) as $new) {
                if ($new->isFirstClassCallable()) {
                    continue;
                }

                foreach ($new->getArgs() as $arg) {
                    if (isset($sites[spl_object_id($arg->value)])) {
                        $classified[spl_object_id($arg->value)] = true;
                    }
                }
            }

            // `$this->other($this->helper())` — allowed only when the receiving parameter is proven; an
            // inherited assertion (`static::assertSame($this->helper(), ...)`) only reads its arguments.
            foreach ($this->findOwnCalls($finder, $stmts) as $call) {
                $assertion = $this->isInheritedAssertionCall($call, $ownMethods);
                $callee = $this->resolveOwnMethod($call, $ownMethods);

                foreach ($call->getArgs() as $index => $arg) {
                    if (!isset($sites[spl_object_id($arg->value)])) {
                        continue;
                    }

                    if ($assertion) {
                        $classified[spl_object_id($arg->value)] = true;

                        continue;
                    }

                    if ($callee === null || !$this->argumentIsNeverExpected($callee, $arg, $index, $ownMethods, [])) {
                        return false;
                    }

                    $classified[spl_object_id($arg->value)] = true;
                }
            }

            // `$this->helper();` as a bare statement — the double is dropped.
            foreach ($finder->findInstanceOf($stmts, Expression::class) as $expression) {
                if (isset($sites[spl_object_id($expression->expr)])) {
                    $classified[spl_object_id($expression->expr)] = true;
                }
            }

            foreach (array_keys($sites) as $id) {
                if (!isset($classified[$id])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * True when every use of local `$$name` in $stmts is provably harmless. Uses listed in $extraHarmless
     * (defining assignments, sanctioned returns) are accepted as-is.
     *
     * @param array<Node> $stmts
     * @param array<string, ClassMethod> $ownMethods
     * @param list<int> $extraHarmless spl_object_ids of Variable nodes to accept
     * @param array<string, true> $visited guards against recursive helpers and alias chains
     */
    private function variableIsNeverExpected(array $stmts, string $name, array $ownMethods, array $extraHarmless = [], array $visited = []): bool
    {
        return $this->referenceIsNeverExpected(
            $stmts,
            static fn (Node $node): bool => $node instanceof Variable && $node->name === $name,
            $ownMethods,
            $extraHarmless,
            $visited,
        );
    }

    /**
     * The shared core of all "is this double ever expected?" analyses. True when every occurrence of the
     * reference matched by $isReference (a local variable or a `$this->prop` fetch) in $stmts is provably
     * harmless: receiving a non-`expects()` call (stub configuration), being handed to a constructor
     * (production code cannot configure expectations), being read by an inherited PHPUnit assertion, or
     * being forwarded to an own method whose parameter is proven. Occurrences listed in $extraHarmless are
     * accepted as-is; any other occurrence — an `->expects()`, a `return`, a re-assignment, an unresolvable
     * call — answers false.
     *
     * @param array<Node> $stmts
     * @param callable(Node): bool $isReference
     * @param array<string, ClassMethod> $ownMethods
     * @param list<int> $extraHarmless spl_object_ids of reference nodes to accept
     * @param array<string, true> $visited guards against recursive helpers
     */
    private function referenceIsNeverExpected(array $stmts, callable $isReference, array $ownMethods, array $extraHarmless = [], array $visited = []): bool
    {
        $finder = new NodeFinder();

        $uses = [];
        foreach ($finder->find($stmts, static fn (Node $node): bool => $isReference($node)) as $node) {
            $uses[spl_object_id($node)] = true;
        }

        if ($uses === []) {
            return true;
        }

        $harmless = array_fill_keys($extraHarmless, true);

        // `$ref->method(...)` — stub configuration is fine, `$ref->expects(...)` is not (unless the caller
        // sanctioned that occurrence via $extraHarmless), and a dynamic method name could be either.
        foreach ($finder->findInstanceOf($stmts, MethodCall::class) as $call) {
            if (!$isReference($call->var) || isset($harmless[spl_object_id($call->var)])) {
                continue;
            }

            if (!$call->name instanceof Identifier || $this->isExpectsCall($call)) {
                return false;
            }

            $harmless[spl_object_id($call->var)] = true;
        }

        // `new Sut($ref)` — production code, it cannot configure expectations.
        foreach ($finder->findInstanceOf($stmts, New_::class) as $new) {
            if ($new->isFirstClassCallable()) {
                continue;
            }

            foreach ($new->getArgs() as $arg) {
                foreach ($this->passedThrough($arg->value, $isReference) as $occurrence) {
                    $harmless[spl_object_id($occurrence)] = true;
                }
            }
        }

        // arguments of PHPUnit's double-configuration chain — `$other->method(...)`, `->with($ref)`,
        // `->willReturnMap([[..., $ref]])` — are consumed as data; the stubbing machinery never
        // configures an expectation on a double passed there.
        foreach ($finder->findInstanceOf($stmts, MethodCall::class) as $call) {
            if (!$call->name instanceof Identifier || !\in_array($call->name->name, self::DOUBLE_CONFIGURATION_METHODS, true)) {
                continue;
            }

            foreach ($call->getArgs() as $arg) {
                foreach ($this->passedThrough($arg->value, $isReference) as $occurrence) {
                    $harmless[spl_object_id($occurrence)] = true;
                }
            }
        }

        // `$ref = $ref ?? <default>` / `$ref ??= <default>` self-defaulting keeps the double flowing
        // through the same reference, whose other uses this analysis already tracks. `$alias = $ref`
        // (incl. behind `??`/ternary defaulting) is followed into the alias, which must itself stay clean.
        foreach ($finder->findInstanceOf($stmts, Assign::class) as $assign) {
            if ($isReference($assign->var)) {
                if ($assign->expr instanceof Coalesce && $isReference($assign->expr->left)) {
                    $harmless[spl_object_id($assign->var)] = true;
                    $harmless[spl_object_id($assign->expr->left)] = true;
                }

                continue; // any other re-assignment of the reference stays unclassified
            }

            if (!$assign->var instanceof Variable || !\is_string($assign->var->name)) {
                continue;
            }

            $occurrences = $this->passedThrough($assign->expr, $isReference);
            $aliasKey = 'alias$' . $assign->var->name;
            if ($occurrences === [] || isset($visited[$aliasKey])) {
                continue;
            }

            if (!$this->variableIsNeverExpected($stmts, $assign->var->name, $ownMethods, [spl_object_id($assign->var)], [...$visited, $aliasKey => true])) {
                continue;
            }

            foreach ($occurrences as $occurrence) {
                $harmless[spl_object_id($occurrence)] = true;
            }
        }

        foreach ($finder->findInstanceOf($stmts, CoalesceAssign::class) as $assign) {
            if ($isReference($assign->var)) {
                $harmless[spl_object_id($assign->var)] = true;
            }
        }

        // `$ref === null` / `$ref !== null` reads the reference without configuring anything, and inside an
        // `if ($ref === null) { ... }` body the tracked double is provably NOT bound to the reference, so
        // every occurrence there (the guarded stub-defaulting of fixture helpers) belongs to the replacement.
        foreach ([...$finder->findInstanceOf($stmts, Identical::class), ...$finder->findInstanceOf($stmts, NotIdentical::class)] as $comparison) {
            foreach ($this->nullComparedReference($comparison->left, $comparison->right, $isReference) as $occurrence) {
                $harmless[spl_object_id($occurrence)] = true;
            }
        }

        foreach ($finder->findInstanceOf($stmts, If_::class) as $if) {
            if (!$if->cond instanceof Identical || $this->nullComparedReference($if->cond->left, $if->cond->right, $isReference) === []) {
                continue;
            }

            foreach ($finder->find($if->stmts, static fn (Node $node): bool => $isReference($node)) as $occurrence) {
                $harmless[spl_object_id($occurrence)] = true;
            }
        }

        // forwarded to an own method (recurse into it) or read by an inherited assertion
        foreach ($this->findOwnCalls($finder, $stmts) as $call) {
            $assertion = $this->isInheritedAssertionCall($call, $ownMethods);
            $callee = $this->resolveOwnMethod($call, $ownMethods);
            if ($callee === null && !$assertion) {
                continue;
            }

            foreach ($call->getArgs() as $index => $arg) {
                $occurrences = $this->passedThrough($arg->value, $isReference);
                if ($occurrences === []) {
                    continue;
                }

                if (!$assertion && ($callee === null || !$this->argumentIsNeverExpected($callee, $arg, $index, $ownMethods, $visited))) {
                    continue;
                }

                foreach ($occurrences as $occurrence) {
                    $harmless[spl_object_id($occurrence)] = true;
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
        // handed back to the caller → covered by findHelperReturnedStubMocks(), which follows it to the call sites
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

        // handed back to the caller → covered by findHelperReturnedStubMocks(), which follows it to the call sites
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
     * @return array<string, list<array{Assign, ?string}>> createMock property assignments with the ancestor
     *                                                     file they live in, keyed by property name
     */
    private function collectPropertyMockAssignments(NodeFinder $finder, array $methods): array
    {
        $assignments = [];
        foreach ($methods as $method) {
            foreach ($finder->findInstanceOf((array) $method->stmts, Assign::class) as $assign) {
                $name = $this->propertyName($assign->var);
                if ($name !== null && $this->isCreateMockCall($assign->expr)) {
                    $assignments[$name][] = [$assign, $this->sourceFile($method)];
                }
            }
        }

        return $assignments;
    }

    /**
     * @param array<ClassMethod> $methods
     * @param array<string, ClassMethod> $ownMethods
     *
     * @return list<array{Assign, string, ?string, ?string}> assignment, message template, mixed-usage detail,
     *                                                       ancestor file the assignment lives in
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
        $callGraph = $this->buildOwnCallGraph($finder, $methods, $ownMethods);

        $errors = [];
        foreach ($assignments as $name => $propAssignments) {
            // A property created in setUp() and re-created inside a test replaces the setUp instance
            // before it is used, orphaning it.
            $setUpAssign = $setUp !== null && !$this->methodExpectsProperty($finder, $setUp, $name)
                ? $this->propertyCreationAssign($finder, $setUp, $name)
                : null;
            if ($setUpAssign !== null) {
                $reassigners = [];
                foreach ($testMethods as $test) {
                    if ($this->propertyCreationAssign($finder, $test, $name) !== null) {
                        $reassigners[] = $test->name->name . '()';
                    }
                }

                if ($reassigners !== []) {
                    $errors[] = [$setUpAssign, self::ERROR_ORPHANED, implode(', ', $reassigners), $this->sourceFile($setUp)];

                    continue;
                }
            }

            if ($this->isPropertyOffLimits($finder, $methods, $helperMethods, $ownMethods, $name)) {
                continue;
            }

            // Every method with a direct `$this->$name->expects(...)`. A test reaching one of them through
            // the own call graph is covered; a helper's expectation only counts for the tests that call it.
            $expectors = [];
            foreach ($ownMethods as $key => $method) {
                if ($this->methodExpectsProperty($finder, $method, $name)) {
                    $expectors[$key] = true;
                }
            }

            // Every method assigning `$this->$name = createMock(...)`. A test owns an instance — and can
            // trigger the notice — when setUp, the test itself, or a fixture helper it calls creates one.
            $creators = [];
            foreach ($ownMethods as $key => $method) {
                if ($this->methodCreatesProperty($finder, $method, $name)) {
                    $creators[$key] = true;
                }
            }

            $coveredBySetUp = $setUp !== null && $this->coversProperty('setup', $expectors, $callGraph);
            $createdInSetUp = $setUp !== null && $this->coversProperty('setup', $creators, $callGraph);

            $bare = [];
            foreach ($testMethods as $test) {
                if (!$createdInSetUp && !$this->coversProperty(mb_strtolower($test->name->name), $creators, $callGraph)) {
                    continue;
                }

                if ($coveredBySetUp || $this->coversProperty(mb_strtolower($test->name->name), $expectors, $callGraph)) {
                    continue;
                }

                $bare[] = $test->name->name . '()';
            }

            if ($bare === []) {
                continue;
            }

            $message = $expectors === [] ? self::ERROR_STUB : self::ERROR_MIXED;
            $detail = $expectors === [] ? null : implode(', ', $bare);
            foreach ($propAssignments as [$assign, $file]) {
                $errors[] = [$assign, $message, $detail, $file];
            }
        }

        return $errors;
    }

    /**
     * The class-internal call graph: for every method, the own methods it calls via `$this->`/`self::`/
     * `static::`, lowercased.
     *
     * @param array<ClassMethod> $methods
     * @param array<string, ClassMethod> $ownMethods
     *
     * @return array<string, list<string>>
     */
    private function buildOwnCallGraph(NodeFinder $finder, array $methods, array $ownMethods): array
    {
        $keys = [];
        foreach ($ownMethods as $key => $method) {
            $keys[spl_object_id($method)] = $key;
        }

        $graph = [];
        foreach ($methods as $method) {
            $callees = [];
            foreach ($this->findOwnCalls($finder, (array) $method->stmts) as $call) {
                $parentTarget = $call->getAttribute(self::PARENT_TARGET);
                if (\is_string($parentTarget)) {
                    $callees[$parentTarget] = true;

                    continue;
                }

                if ($call->name instanceof Identifier && isset($ownMethods[mb_strtolower($call->name->name)])) {
                    $callees[mb_strtolower($call->name->name)] = true;
                }
            }

            $graph[$keys[spl_object_id($method)] ?? mb_strtolower($method->name->name)] = array_keys($callees);
        }

        return $graph;
    }

    /**
     * True when $methodName — or any own method it transitively calls — is one of the $targets: the methods
     * that directly `->expects()` the property (coverage walk), or the ones that create it (ownership walk).
     *
     * @param array<string, true> $targets lowercased method names
     * @param array<string, list<string>> $callGraph
     */
    private function coversProperty(string $methodName, array $targets, array $callGraph): bool
    {
        $queue = [$methodName];
        $visited = [];
        while ($queue !== []) {
            $current = array_pop($queue);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            if (isset($targets[$current])) {
                return true;
            }

            foreach ($callGraph[$current] ?? [] as $callee) {
                $queue[] = $callee;
            }
        }

        return false;
    }

    /**
     * A property whose expectations could be configured out of view — passed into a `$this->`/`self::`/
     * `static::` call, or used unsafely by a non-test/non-setUp helper — cannot be reasoned about safely.
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

        return $this->helperUsageOfPropertyIsUnsafe($finder, $helperMethods, $ownMethods, $name);
    }

    /**
     * True when some helper method uses `$this->$name` in a way that could configure an expectation out of
     * view: anything that is neither stub configuration, forwarding, an inherited-assertion read, a direct
     * `->expects()` on the property, nor its creating assignment. The direct `->expects()` and the creation
     * are not hidden — the per-test coverage and ownership walks ({@see self::coversProperty()}) attribute
     * them to the tests that call the helper.
     *
     * @param array<ClassMethod> $helperMethods
     * @param array<string, ClassMethod> $ownMethods
     */
    private function helperUsageOfPropertyIsUnsafe(NodeFinder $finder, array $helperMethods, array $ownMethods, string $name): bool
    {
        $isReference = fn (Node $node): bool => $this->propertyName($node) === $name;

        foreach ($helperMethods as $helper) {
            $stmts = (array) $helper->stmts;
            if ($finder->findFirst($stmts, static fn (Node $node): bool => $isReference($node)) === null) {
                continue;
            }

            $sanctioned = [];
            foreach ($finder->findInstanceOf($stmts, MethodCall::class) as $call) {
                if ($this->isExpectsCall($call) && $isReference($call->var)) {
                    $sanctioned[] = spl_object_id($call->var);
                }
            }
            foreach ($finder->findInstanceOf($stmts, Assign::class) as $assign) {
                if ($isReference($assign->var) && $this->isCreateMockCall($assign->expr)) {
                    $sanctioned[] = spl_object_id($assign->var);
                }
            }

            if (!$this->referenceIsNeverExpected($stmts, $isReference, $ownMethods, $sanctioned)) {
                return true;
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
        return $this->propertyCreationAssign($finder, $method, $name) !== null;
    }

    /**
     * The first `$this->$name = $this->createMock(...)` assignment in $method, or null if there is none.
     */
    private function propertyCreationAssign(NodeFinder $finder, ClassMethod $method, string $name): ?Assign
    {
        foreach ($finder->findInstanceOf((array) $method->stmts, Assign::class) as $assign) {
            if ($this->propertyName($assign->var) === $name && $this->isCreateMockCall($assign->expr)) {
                return $assign;
            }
        }

        return null;
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
        $resolvableMethods = $ownMethods;
        if ($this->hasOpaqueExpectsReceiver($finder, $stmts)) {
            // Some double is ->expects()-ed through a reference this rule cannot resolve (typically a fixture
            // struct handed back by the helper: `$fixture->repository->expects(...)`). Which double that is
            // cannot be told, so no callee counts as transparent here.
            $resolvableMethods = [];
        }

        foreach ($this->findOwnCalls($finder, $stmts) as $call) {
            // `static::assertSame($double, ...)` — an inherited PHPUnit assertion only reads its arguments;
            // it cannot configure expectations. Checked against the full method map on purpose: an
            // assert-named method declared in this class is analysed like any other helper.
            if ($this->isInheritedAssertionCall($call, $ownMethods)) {
                continue;
            }

            $callee = $this->resolveOwnMethod($call, $resolvableMethods);

            foreach ($call->getArgs() as $index => $arg) {
                if ($callee !== null && $this->argumentIsNeverExpected($callee, $arg, $index, $resolvableMethods, [])) {
                    continue;
                }

                $onArg($arg);
            }
        }
    }

    /**
     * A `$this->`/`self::`/`static::` call to an `assert*` method that is not declared in this class — an
     * inherited PHPUnit assertion. Assertions only read their arguments and can never configure an
     * expectation on a double.
     *
     * @param array<string, ClassMethod> $ownMethods
     */
    private function isInheritedAssertionCall(MethodCall|StaticCall $call, array $ownMethods): bool
    {
        if (!$call->name instanceof Identifier) {
            return false;
        }

        $name = mb_strtolower($call->name->name);

        return \str_starts_with($name, 'assert') && !isset($ownMethods[$name]);
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
            if (!$call->class instanceof Name || $call->isFirstClassCallable()) {
                continue;
            }

            // `parent::x()` counts only when {@see self::linkParentCalls()} resolved it into a shadowed
            // ancestor declaration; an unresolved parent call targets a vendor base (TestCase itself),
            // which cannot configure expectations on this class's doubles.
            if (\in_array(mb_strtolower($call->class->toString()), ['self', 'static'], true) || \is_string($call->getAttribute(self::PARENT_TARGET))) {
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
        foreach ($this->findScopedReturns($stmts) as $return) {
            if ($return->expr === null) {
                continue;
            }

            // A double wrapped in a returned `new <production class>(...)` does not reach the caller:
            // production code cannot configure expectations and does not re-expose the double. A returned
            // `new <test-namespace fixture>(...)` DOES hand it back (public fixture properties), so its
            // arguments stay counted.
            $shielded = [];
            foreach ($finder->findInstanceOf([$return->expr], New_::class) as $new) {
                if (!$this->isProductionClassNew($new)) {
                    continue;
                }

                foreach ($finder->findInstanceOf($new->getArgs(), Variable::class) as $variable) {
                    $shielded[spl_object_id($variable)] = true;
                }
            }

            foreach ($finder->findInstanceOf([$return->expr], Variable::class) as $variable) {
                if (isset($shielded[spl_object_id($variable)])) {
                    continue;
                }

                $name = $this->localName($variable);
                if ($name !== null) {
                    $names[$name] = true;
                }
            }
        }

        return array_keys($names);
    }

    /**
     * The `return` statements that leave the analysed method itself. A `return` inside a nested closure ends
     * that closure, not the method — the fluent `->willReturnCallback(function () use ($double) { return $double; })`
     * idiom hands the double to the SUT's call chain, never back to the method's caller. A closure that itself
     * leaves through a method-level `return` still escapes with its captures: those returns are collected here
     * and their full expression (the closure included) is scanned by the caller.
     *
     * @param array<Node> $stmts
     *
     * @return list<Return_>
     */
    private function findScopedReturns(array $stmts): array
    {
        $visitor = new class extends NodeVisitorAbstract {
            /**
             * @var list<Return_>
             */
            public array $returns = [];

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Closure || $node instanceof ArrowFunction) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                if ($node instanceof Return_) {
                    $this->returns[] = $node;
                }

                return null;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);

        return $visitor->returns;
    }

    /**
     * The reference occurrences of a `$ref === null` / `null !== $ref` comparison — a read that cannot
     * configure an expectation.
     *
     * @param callable(Node): bool $isReference
     *
     * @return list<Expr>
     */
    private function nullComparedReference(Expr $left, Expr $right, callable $isReference): array
    {
        $isNull = static fn (Expr $expr): bool => $expr instanceof ConstFetch && mb_strtolower($expr->name->toString()) === 'null';

        if ($isReference($left) && $isNull($right)) {
            return [$left];
        }

        if ($isReference($right) && $isNull($left)) {
            return [$right];
        }

        return [];
    }

    /**
     * True when the instantiated class is resolvable production code: its constructor cannot configure
     * expectations and does not re-expose the double to the caller — unlike a fixture struct from a test
     * namespace, whose public properties hand the double back for a later `->expects()`.
     */
    private function isProductionClassNew(New_ $new): bool
    {
        if (!$new->class instanceof Name) {
            return false;
        }

        $resolved = $new->class->getAttribute('resolvedName');
        $className = $resolved instanceof Name ? $resolved->toString() : $new->class->toString();

        return !$this->isEnabledNamespace($className) && !str_starts_with($className, 'Shopware\Tests\\');
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

        $parentTarget = $call->getAttribute(self::PARENT_TARGET);
        if (\is_string($parentTarget)) {
            return $ownMethods[$parentTarget] ?? null;
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
        return $this->referenceIsNeverExpected(
            (array) $method->stmts,
            static fn (Node $node): bool => $node instanceof Variable && $node->name === $paramName,
            $ownMethods,
            [],
            $visited,
        );
    }

    /**
     * The reference occurrences that $expr passes on unchanged — the bare reference, one behind the
     * defaulting wrappers fixture helpers use (`$param ?? $this->shared`, `$param ?: $fallback`), or one
     * wrapped in an array literal (`[$ref]`, `['key' => $ref]`; a receiver can only get at the element
     * through an access this analysis leaves unclassified, so wrapping loses no soundness). Occurrences
     * anywhere else in $expr are not passed on unchanged and are left for the caller to reject.
     *
     * @param callable(Node): bool $isReference
     *
     * @return list<Expr>
     */
    private function passedThrough(Expr $expr, callable $isReference): array
    {
        if ($isReference($expr)) {
            return [$expr];
        }

        if ($expr instanceof Coalesce) {
            return [...$this->passedThrough($expr->left, $isReference), ...$this->passedThrough($expr->right, $isReference)];
        }

        if ($expr instanceof Ternary) {
            return [
                ...$this->passedThrough($expr->if ?? $expr->cond, $isReference),
                ...$this->passedThrough($expr->else, $isReference),
            ];
        }

        if ($expr instanceof Array_) {
            $occurrences = [];
            foreach ($expr->items as $item) {
                if ($item->byRef || $item->unpack) {
                    continue;
                }

                $occurrences = [...$occurrences, ...$this->passedThrough($item->value, $isReference)];
            }

            return $occurrences;
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

    private function buildError(Node $createMockCall, int $line, string $message, ?string $detail = null, ?string $file = null): RuleError
    {
        $label = $this->resolveMockedClass($createMockCall) ?? '...';

        $builder = RuleErrorBuilder::message(\sprintf($message, $label, $detail ?? $label))
            ->identifier('shopware.createMockWithoutExpectations')
            ->line($line);

        if ($file !== null) {
            $builder->file($file);
        }

        return $builder->build();
    }

    private function isEnabledNamespace(string $className): bool
    {
        return self::matchesAny($className, $this->enabledNamespaces);
    }

    /**
     * @param list<string> $namespaces
     */
    private static function matchesAny(string $className, array $namespaces): bool
    {
        foreach ($namespaces as $namespace) {
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
