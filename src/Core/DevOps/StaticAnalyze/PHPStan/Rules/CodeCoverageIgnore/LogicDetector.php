<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class LogicDetector
{
    /**
     * Branching, error-path and value-mutating constructs. Plain calls, instantiation,
     * arithmetic and coalesce are intentionally absent — they're not branching by
     * themselves, and the called code has its own coverage story. Compound assignment,
     * increment/decrement and unset are present because they transform a value in place:
     * a method doing that is shaping a result, not passing one through.
     */
    private const LOGIC_NODE_TYPES = [
        Stmt\If_::class,
        Stmt\ElseIf_::class,
        Stmt\Else_::class,
        Stmt\Switch_::class,
        Expr\Match_::class,
        Stmt\While_::class,
        Stmt\Do_::class,
        Stmt\For_::class,
        Stmt\Foreach_::class,
        Stmt\TryCatch::class,
        Stmt\Catch_::class,
        Expr\Throw_::class,
        Expr\Ternary::class,
        Stmt\Unset_::class,
        Expr\AssignOp::class,
        Expr\PreInc::class,
        Expr\PostInc::class,
        Expr\PreDec::class,
        Expr\PostDec::class,
    ];

    /**
     * Plain conditionals are exempt inside \Throwable subclasses: an exception factory
     * that branches between returning one exception or another (feature-flag forks,
     * message variants) selects an error shape, it does not implement business logic.
     * Loops, try/catch, switch/match, multi-statement throws and value mutation still count.
     */
    private const THROWABLE_EXEMPT_NODE_TYPES = [
        Stmt\If_::class,
        Stmt\ElseIf_::class,
        Stmt\Else_::class,
        Expr\Ternary::class,
    ];

    private function __construct()
    {
    }

    public static function methodContainsLogic(ClassMethod $method, bool $inThrowableContext = false): bool
    {
        if ($method->stmts === null) {
            return false;
        }

        // Single-throw bodies are contract markers (decoration-pattern stubs,
        // "this method must be overridden", unreachable guards). The throw is
        // not behaviour worth covering — it is the absence of an implementation.
        if (self::isSingleThrowStub($method->stmts)) {
            return false;
        }

        $logicNodeTypes = self::LOGIC_NODE_TYPES;
        if ($inThrowableContext) {
            $logicNodeTypes = array_values(array_diff($logicNodeTypes, self::THROWABLE_EXEMPT_NODE_TYPES));
        }

        $hit = (new NodeFinder())->findFirst($method->stmts, static function (Node $node) use ($logicNodeTypes): bool {
            foreach ($logicNodeTypes as $type) {
                if ($node instanceof $type) {
                    return true;
                }
            }

            return false;
        });

        if ($hit !== null) {
            return true;
        }

        if (self::callsItselfForEffect($method->stmts)) {
            return true;
        }

        // Message-variant if/else arms legitimately write the same local once per arm;
        // with the branches exempt, counting those writes would re-flag them.
        if ($inThrowableContext) {
            return false;
        }

        return self::rewritesALocal($method);
    }

    /**
     * A bare `$this->guard()`, `self::init()` or `static::validate()` statement whose result
     * is discarded is the class running its own behaviour for its side effect — access
     * guards, hooks, registrations. That behaviour (and its error path) belongs to this
     * class, so the method is not a pass-through accessor. `parent::` calls stay exempt:
     * they chain to behaviour the parent is responsible for covering. Calls on
     * collaborators (`$this->dep->call()`) are delegation and stay exempt as well.
     *
     * @param array<Stmt> $stmts
     */
    private static function callsItselfForEffect(array $stmts): bool
    {
        $hit = (new NodeFinder())->findFirst($stmts, static function (Node $node): bool {
            if (!$node instanceof Stmt\Expression) {
                return false;
            }

            $call = $node->expr;
            if ($call instanceof Expr\MethodCall || $call instanceof Expr\NullsafeMethodCall) {
                return $call->var instanceof Expr\Variable && $call->var->name === 'this';
            }

            if ($call instanceof Expr\StaticCall && $call->class instanceof Node\Name) {
                return \in_array($call->class->toLowerString(), ['self', 'static'], true);
            }

            return false;
        });

        return $hit !== null;
    }

    /**
     * @param array<Stmt> $stmts
     */
    private static function isSingleThrowStub(array $stmts): bool
    {
        if (\count($stmts) !== 1) {
            return false;
        }

        $first = $stmts[0];

        return $first instanceof Stmt\Expression && $first->expr instanceof Expr\Throw_;
    }

    /**
     * A local variable written a second time — as a whole, through an offset or through
     * a property — is a value being transformed step by step (`$values = parse(); $values =
     * array_merge($values, …)`, `$data = []; $data['x'] = …`). Parameters count as already
     * written, so reassigning one (`$name = trim($name)`) is a transformation as well.
     * Writes on `$this` are state, not a local, and stay boilerplate. Closures and arrow
     * functions have their own scope and are skipped.
     *
     * Compound assignment and unset are already caught as node types above, so only plain
     * (reference) assignments need counting here.
     */
    private static function rewritesALocal(ClassMethod $method): bool
    {
        \assert($method->stmts !== null);

        $written = [];
        foreach ($method->params as $param) {
            if ($param->var instanceof Expr\Variable && \is_string($param->var->name)) {
                $written[$param->var->name] = true;
            }
        }

        $finder = new NodeFinder();

        $scoped = [];
        foreach ($finder->find($method->stmts, static fn (Node $node): bool => $node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) as $function) {
            foreach ($finder->findInstanceOf([$function], Expr\Assign::class) as $inner) {
                $scoped[spl_object_id($inner)] = true;
            }
            foreach ($finder->findInstanceOf([$function], Expr\AssignRef::class) as $inner) {
                $scoped[spl_object_id($inner)] = true;
            }
        }

        $assignments = [
            ...$finder->findInstanceOf($method->stmts, Expr\Assign::class),
            ...$finder->findInstanceOf($method->stmts, Expr\AssignRef::class),
        ];
        usort($assignments, static fn (Node $a, Node $b): int => $a->getStartFilePos() <=> $b->getStartFilePos());

        foreach ($assignments as $assignment) {
            if (isset($scoped[spl_object_id($assignment)])) {
                continue;
            }

            foreach (self::localRoots($assignment->var) as $name) {
                if (isset($written[$name])) {
                    return true;
                }
                $written[$name] = true;
            }
        }

        return false;
    }

    /**
     * Names of the local variables an assignment target ultimately writes to. `$this`
     * and static properties yield nothing; destructuring yields every item.
     *
     * @return list<string>
     */
    private static function localRoots(Node $target): array
    {
        if ($target instanceof Expr\Variable) {
            return \is_string($target->name) && $target->name !== 'this' ? [$target->name] : [];
        }

        if ($target instanceof Expr\ArrayDimFetch || $target instanceof Expr\PropertyFetch || $target instanceof Expr\NullsafePropertyFetch) {
            return self::localRoots($target->var);
        }

        if ($target instanceof Expr\List_ || $target instanceof Expr\Array_) {
            $roots = [];
            foreach ($target->items as $item) {
                if ($item instanceof Node\ArrayItem) {
                    $roots = [...$roots, ...self::localRoots($item->value)];
                }
            }

            return $roots;
        }

        return [];
    }
}
