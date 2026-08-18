<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Case_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitorAbstract;
use Shopware\Core\Framework\Log\Package;

/**
 * Annotates every method call inside a loop body with the chain of loops enclosing it, so that rules working on
 * method calls (which have no access to the parent node chain) can detect calls that run once per iteration.
 *
 * Only calls inside the loop *body* are annotated, never calls in the loop header, so that the "fetch the next
 * chunk" call of a pagination loop is not treated as being inside the loop itself.
 *
 * The context describes the shapes in which a single iteration already handles a whole set of records:
 *
 * - `bounded`: the loop runs a fixed, statically visible number of times.
 * - `drain`: a `while`/`do-while` that consumes a paginated source or a worklist until it is exhausted.
 * - `chunkProducer`: the call in a `while`/`do-while` condition that advances the loop, e.g.
 *   `while ($ids = $iterator->fetch())`. Whether it hands out a whole chunk depends on its type.
 * - `batchVariables`: the value variables of a `foreach`, e.g. `$chunk` of `foreach ($chunks as $chunk)`. Whether
 *   they hold a batch of values rather than a single record depends on their type.
 *
 * The two type-dependent entries are decided by the consuming rule, which has a {@see \PHPStan\Analyser\Scope}.
 *
 * @see NoQueryInLoopRule
 *
 * @phpstan-type LoopContext array{bounded: bool, drain: bool, chunkProducer: Expr|null, batchVariables: list<string>}
 *
 * @internal
 */
#[Package('framework')]
class LoopContextVisitor extends NodeVisitorAbstract
{
    public const ATTRIBUTE = 'shopwareEnclosingLoops';

    /**
     * A `LIMIT` that is bound to a parameter or to a literal greater than one fetches a page of records. `LIMIT 1`
     * is excluded: it reads a single record and is the typical shape of an N+1 lookup.
     */
    private const PAGINATED_LIMIT_REGEX = '/\bLIMIT\s+(?::\w+|\?|[2-9]|\d{2,})/i';

    /**
     * @var list<FunctionLike>
     */
    private array $functionStack = [];

    /**
     * @param array<Node> $nodes
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->functionStack = [];

        return null;
    }

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof FunctionLike) {
            $this->functionStack[] = $node;

            return null;
        }

        if (!$node instanceof Foreach_ && !$node instanceof For_ && !$node instanceof While_ && !$node instanceof Do_) {
            return null;
        }

        $context = [
            'bounded' => $this->isBoundedLoop($node),
            'drain' => $this->isDrainLoop($node),
            'chunkProducer' => $this->findChunkProducer($node),
            'batchVariables' => $this->findBatchVariables($node),
        ];

        $runOnce = $this->findCallsRunningAtMostOnce($node->stmts);

        // outer loops are visited before inner ones, so appending builds an outermost-first chain
        foreach ((new NodeFinder())->findInstanceOf($node->stmts, MethodCall::class) as $call) {
            if (isset($runOnce[spl_object_id($call)])) {
                continue;
            }

            /** @var list<LoopContext> $loops */
            $loops = $call->getAttribute(self::ATTRIBUTE, []);
            $loops[] = $context;

            $call->setAttribute(self::ATTRIBUTE, $loops);
        }

        return null;
    }

    public function leaveNode(Node $node): ?Node
    {
        if ($node instanceof FunctionLike) {
            array_pop($this->functionStack);
        }

        return null;
    }

    /**
     * Calls that a single iteration reaches at most once for the whole loop, so they do not scale with the number of
     * records even though they sit in the loop body:
     *
     * - everything in a block that ends in `throw` or `return`, because that block leaves the loop, e.g. the cleanup
     *   query of an error handler that rethrows,
     * - a query memoised by a null check, e.g. `if ($sets === null) { $sets = $this->connection->fetchAll…(); }`.
     *
     * @param array<Node\Stmt> $stmts
     *
     * @return array<int, true> keyed by the calls' object ids
     */
    private function findCallsRunningAtMostOnce(array $stmts): array
    {
        $calls = [];

        $blocks = [$stmts];
        foreach ((new NodeFinder())->find($stmts, static fn (Node $node): bool => $node instanceof If_ || $node instanceof ElseIf_ || $node instanceof Else_ || $node instanceof Catch_ || $node instanceof Case_) as $block) {
            /** @var If_|ElseIf_|Else_|Catch_|Case_ $block */
            $blocks[] = $block->stmts;

            if ($block instanceof If_ && $this->isNullCheck($block->cond) !== null) {
                foreach ($this->findMemoisingAssignments($block) as $assignment) {
                    foreach ((new NodeFinder())->findInstanceOf([$assignment->expr], MethodCall::class) as $call) {
                        $calls[spl_object_id($call)] = true;
                    }
                }
            }
        }

        foreach ($blocks as $block) {
            if (!$this->leavesTheLoop($block)) {
                continue;
            }

            foreach ((new NodeFinder())->findInstanceOf($block, MethodCall::class) as $call) {
                $calls[spl_object_id($call)] = true;
            }
        }

        return $calls;
    }

    /**
     * @param array<Node\Stmt> $stmts
     */
    private function leavesTheLoop(array $stmts): bool
    {
        $last = $stmts === [] ? null : $stmts[array_key_last($stmts)];

        if ($last instanceof Return_) {
            return true;
        }

        return $last instanceof Expression && $last->expr instanceof Throw_;
    }

    /**
     * The assignments of an `if` that guards against an already resolved value, e.g. the `$sets = …` of
     * `if ($sets === null) { $sets = … }`.
     *
     * @return list<Assign>
     */
    private function findMemoisingAssignments(If_ $if): array
    {
        $guarded = $this->isNullCheck($if->cond);

        if ($guarded === null) {
            return [];
        }

        $assignments = [];
        foreach ((new NodeFinder())->findInstanceOf($if->stmts, Assign::class) as $assign) {
            if ($this->describeTarget($assign->var) === $guarded) {
                $assignments[] = $assign;
            }
        }

        return $assignments;
    }

    /**
     * The target of a `=== null` / `!== null` comparison, described so that it can be compared to an assignment
     * target. Returns null when the condition is not a null check.
     */
    private function isNullCheck(Expr $condition): ?string
    {
        if (!$condition instanceof Identical && !$condition instanceof NotIdentical && !$condition instanceof Equal && !$condition instanceof NotEqual) {
            return null;
        }

        foreach ([[$condition->left, $condition->right], [$condition->right, $condition->left]] as [$target, $value]) {
            if ($value instanceof ConstFetch && strtolower($value->name->toString()) === 'null') {
                return $this->describeTarget($target);
            }
        }

        return null;
    }

    private function describeTarget(Expr $target): ?string
    {
        if ($target instanceof Variable && \is_string($target->name)) {
            return '$' . $target->name;
        }

        if ($target instanceof PropertyFetch && $target->name instanceof Node\Identifier) {
            $object = $this->describeTarget($target->var);

            return $object === null ? null : $object . '->' . $target->name->toString();
        }

        return null;
    }

    private function isBoundedLoop(Foreach_|For_|While_|Do_ $loop): bool
    {
        // a literal array or a chunked source runs a fixed, reviewable number of times
        if ($loop instanceof Foreach_) {
            return $loop->expr instanceof Array_ || $this->containsChunkCall($loop->expr);
        }

        // a bound against a literal integer runs a fixed number of times, e.g. `for ($i = 0; $i < 3; ++$i)`
        if ($loop instanceof For_) {
            return (new NodeFinder())->findFirst($loop->cond, static fn (Node $node): bool => $node instanceof Int_) !== null;
        }

        return false;
    }

    /**
     * Recognises the two `while`/`do-while` shapes that walk a source page by page instead of record by record: a
     * worklist that is consumed until it is empty (`while ($pendingIds !== [])`), and a loop whose enclosing
     * function paginates, i.e. limits or offsets its query (`while (true) { ... LIMIT :limit ... }`).
     */
    private function isDrainLoop(Foreach_|For_|While_|Do_ $loop): bool
    {
        if (!$loop instanceof While_ && !$loop instanceof Do_) {
            return false;
        }

        return $this->testsForEmptiness($loop->cond) || $this->paginatesInEnclosingFunction();
    }

    private function testsForEmptiness(Expr $condition): bool
    {
        if ($condition instanceof BooleanNot) {
            return $this->testsForEmptiness($condition->expr);
        }

        if ($condition instanceof Empty_) {
            return true;
        }

        if ($condition instanceof Identical || $condition instanceof NotIdentical || $condition instanceof Equal || $condition instanceof NotEqual) {
            return $this->isEmptyArray($condition->left) || $this->isEmptyArray($condition->right);
        }

        return false;
    }

    private function isEmptyArray(Expr $expr): bool
    {
        return $expr instanceof Array_ && $expr->items === [];
    }

    private function paginatesInEnclosingFunction(): bool
    {
        $function = $this->functionStack === [] ? null : $this->functionStack[\count($this->functionStack) - 1];
        $stmts = $function?->getStmts();

        if ($stmts === null) {
            return false;
        }

        return (new NodeFinder())->findFirst($stmts, static function (Node $node): bool {
            if ($node instanceof MethodCall) {
                return $node->name instanceof Node\Identifier
                    && \in_array($node->name->toString(), ['setLimit', 'setOffset'], true);
            }

            return $node instanceof String_ && preg_match(self::PAGINATED_LIMIT_REGEX, $node->value) === 1;
        }) !== null;
    }

    private function findChunkProducer(Foreach_|For_|While_|Do_ $loop): ?Expr
    {
        if (!$loop instanceof While_ && !$loop instanceof Do_) {
            return null;
        }

        return $this->unwrapChunkProducer($loop->cond);
    }

    /**
     * Extracts the call that advances a pagination loop, e.g. the `fetch()` of `while ($ids = $iterator->fetch())`
     * or of `while (($ids = $iterator->fetch()) !== null)`. Conditions that only compare values, such as
     * `while ($offset < \count($ids))`, do not advance anything and therefore have no producer.
     */
    private function unwrapChunkProducer(Expr $condition): ?Expr
    {
        if ($condition instanceof Assign) {
            return $this->findFirstCall($condition->expr);
        }

        if ($condition instanceof BooleanNot) {
            return $this->unwrapChunkProducer($condition->expr);
        }

        if ($condition instanceof Identical || $condition instanceof NotIdentical || $condition instanceof Equal || $condition instanceof NotEqual) {
            return $this->unwrapChunkProducer($condition->left) ?? $this->unwrapChunkProducer($condition->right);
        }

        if ($this->isCall($condition)) {
            return $condition;
        }

        return null;
    }

    /**
     * Names of the variables a `foreach` binds per iteration, including the ones bound by a destructuring pattern
     * such as `foreach ($chunk as ['ids' => $chunkIds])`.
     *
     * @return list<string>
     */
    private function findBatchVariables(Foreach_|For_|While_|Do_ $loop): array
    {
        if (!$loop instanceof Foreach_) {
            return [];
        }

        $variables = [];

        if ($loop->valueVar instanceof Array_ || $loop->valueVar instanceof List_) {
            $variables = (new NodeFinder())->findInstanceOf([$loop->valueVar], Variable::class);
        } elseif ($loop->valueVar instanceof Variable) {
            $variables = [$loop->valueVar];
        }

        $names = [];
        foreach ($variables as $variable) {
            if (\is_string($variable->name)) {
                $names[] = $variable->name;
            }
        }

        return $names;
    }

    private function findFirstCall(Expr $expr): ?Expr
    {
        $call = (new NodeFinder())->findFirst([$expr], fn (Node $node): bool => $this->isCall($node));

        return $call instanceof Expr ? $call : null;
    }

    private function isCall(Node $node): bool
    {
        return $node instanceof MethodCall || $node instanceof StaticCall || $node instanceof FuncCall;
    }

    private function containsChunkCall(Expr $expr): bool
    {
        return (new NodeFinder())->findFirst([$expr], static function (Node $node): bool {
            if ($node instanceof FuncCall) {
                return $node->name instanceof Node\Name && str_contains(strtolower($node->name->toString()), 'chunk');
            }

            if ($node instanceof MethodCall || $node instanceof StaticCall) {
                return $node->name instanceof Node\Identifier && str_contains(strtolower($node->name->toString()), 'chunk');
            }

            return false;
        }) !== null;
    }
}
