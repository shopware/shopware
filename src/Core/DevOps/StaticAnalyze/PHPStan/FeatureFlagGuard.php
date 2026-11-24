<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use Shopware\Core\Framework\Adapter\Twig\Extension\FeatureFlagExtension;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-type Proof array{truthy: bool, falsey: bool}
 * @phpstan-type Aliases array<string, Proof>
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[Package('framework')]
final class FeatureFlagGuard
{
    /**
     * @param array<string, FeatureFlagConfig> $features
     */
    public function __construct(private readonly Node $target, private readonly string $major, private readonly array $features)
    {
    }

    /**
     * @param array<Stmt> $nodes
     */
    public function matches(array $nodes): bool
    {
        $function = null;
        foreach ((new NodeFinder())->findInstanceOf($nodes, Node\FunctionLike::class) as $candidate) {
            if ($this->contains($candidate) && ($function === null || $candidate->getStartFilePos() >= $function->getStartFilePos())) {
                $function = $candidate;
            }
        }

        return $this->statements($function?->getStmts() ?? [], [], false);
    }

    /**
     * @param array<Node> $nodes
     * @param Aliases $aliases
     */
    private function statements(array $nodes, array $aliases, bool $guarded): bool
    {
        foreach ($nodes as $node) {
            if ($this->contains($node)) {
                return $this->trace($node, $aliases, $guarded);
            }
            if ($node->getStartFilePos() > $this->target->getStartFilePos()) {
                break;
            }
            $before = $aliases;
            if ($aliases !== []) {
                foreach ([Expr\Assign::class, Expr\AssignRef::class, Expr\AssignOp::class, Expr\PreInc::class, Expr\PostInc::class, Expr\PreDec::class, Expr\PostDec::class] as $type) {
                    foreach ((new NodeFinder())->findInstanceOf($node, $type) as $assignment) {
                        $aliases = $this->forget($aliases, $assignment->var);
                        if ($assignment instanceof Expr\AssignRef) {
                            $aliases = $this->forget($aliases, $assignment->expr);
                        }
                    }
                }
                foreach ((new NodeFinder())->findInstanceOf($node, Stmt\Unset_::class) as $unset) {
                    foreach ($unset->vars as $variable) {
                        $aliases = $this->forget($aliases, $variable);
                    }
                }
                foreach ((new NodeFinder())->findInstanceOf($node, Node\Arg::class) as $argument) {
                    // A called function may accept this value by reference.
                    $aliases = $this->forget($aliases, $argument->value);
                }
            }
            if ($node instanceof Stmt\Expression && $node->expr instanceof Expr\Assign && ($key = $this->key($node->expr->var)) !== null) {
                $proof = $this->proof($node->expr->expr, $before);
                if ($proof['truthy'] || $proof['falsey']) {
                    $aliases[$key] = $proof;
                }
            }
        }

        return false;
    }

    /**
     * @param Aliases $aliases
     */
    private function trace(Node $node, array $aliases, bool $guarded): bool
    {
        if ($guarded) {
            return true;
        }
        if ($node instanceof Stmt\If_) {
            foreach ([$node, ...$node->elseifs] as $branch) {
                if ($this->contains($branch->cond)) {
                    return $this->trace($branch->cond, $aliases, $guarded);
                }
                $proof = $this->proof($branch->cond, $aliases);
                foreach ($branch->stmts as $statement) {
                    if ($this->contains($statement)) {
                        return $this->statements($branch->stmts, $aliases, $guarded || $proof['truthy']);
                    }
                }
                $guarded = $guarded || $proof['falsey'];
            }

            return $node->else !== null && $this->statements($node->else->stmts, $aliases, $guarded);
        }
        if ($node instanceof Expr\BinaryOp\BooleanAnd && $this->contains($node->right)) {
            return $this->trace($node->right, $aliases, $this->proof($node->left, $aliases)['truthy']);
        }
        if ($node instanceof Expr\BinaryOp\BooleanOr && $this->contains($node->right)) {
            return $this->trace($node->right, $aliases, $this->proof($node->left, $aliases)['falsey']);
        }
        if ($node instanceof Expr\Ternary) {
            $proof = $this->proof($node->cond, $aliases);
            if ($node->if !== null && $this->contains($node->if)) {
                return $this->trace($node->if, $aliases, $proof['truthy']);
            }
            if ($this->contains($node->else)) {
                return $this->trace($node->else, $aliases, $proof['falsey']);
            }
        }
        foreach (get_object_vars($node) as $children) {
            foreach (\is_array($children) ? $children : [$children] as $child) {
                if ($child instanceof Node && $this->contains($child)) {
                    // Do not carry alias assumptions through loops or nested function boundaries.
                    return $this->trace($child, [], false);
                }
            }
        }

        return false;
    }

    /**
     * @param Aliases $aliases
     *
     * @return Proof
     */
    private function proof(Expr $expression, array $aliases): array
    {
        $unknown = ['truthy' => false, 'falsey' => false];
        if (($key = $this->key($expression)) !== null) {
            return $aliases[$key] ?? $unknown;
        }
        if ($expression instanceof Expr\BooleanNot) {
            $proof = $this->proof($expression->expr, $aliases);

            return ['truthy' => $proof['falsey'], 'falsey' => $proof['truthy']];
        }
        if ($expression instanceof Expr\BinaryOp\BooleanAnd || $expression instanceof Expr\BinaryOp\BooleanOr) {
            $left = $this->proof($expression->left, $aliases);
            $right = $this->proof($expression->right, $aliases);

            return $expression instanceof Expr\BinaryOp\BooleanAnd
                ? ['truthy' => $left['truthy'] || $right['truthy'], 'falsey' => $left['falsey'] && $right['falsey']]
                : ['truthy' => $left['truthy'] && $right['truthy'], 'falsey' => $left['falsey'] || $right['falsey']];
        }
        if (!$expression instanceof Expr\StaticCall && !$expression instanceof Expr\MethodCall) {
            return $unknown;
        }
        $feature = $expression instanceof Expr\StaticCall && $expression->class instanceof Node\Name && $expression->class->toString() === Feature::class && $expression->name instanceof Node\Identifier && $expression->name->toString() === 'isActive';
        if ($expression instanceof Expr\MethodCall && $expression->name instanceof Node\Identifier && $expression->name->toString() === 'feature') {
            $receiver = $expression->var;
            $feature = $receiver instanceof Expr\MethodCall && $receiver->name instanceof Node\Identifier && $receiver->name->toString() === 'getExtension'
                && ($receiver->getArgs()[0]->value ?? null) instanceof Node\Scalar\String_
                && $receiver->getArgs()[0]->value->value === FeatureFlagExtension::class;
        }
        if (!$feature || !($expression->getArgs()[0]->value ?? null) instanceof Node\Scalar\String_) {
            return $unknown;
        }
        $flag = Feature::normalizeName($expression->getArgs()[0]->value->value);
        $version = Feature::isMajorVersionFlag($flag) ? $flag : ($this->features[$flag]['major'] ?? null);

        return ['truthy' => false, 'falsey' => \is_string($version) && Feature::normalizeName($version) === $this->major];
    }

    private function contains(Node $node): bool
    {
        return $node->getStartFilePos() <= $this->target->getStartFilePos() && $node->getEndFilePos() >= $this->target->getEndFilePos();
    }

    private function key(Expr $expression): ?string
    {
        if ($expression instanceof Expr\Variable && \is_string($expression->name)) {
            return '$' . $expression->name;
        }
        if ($expression instanceof Expr\ArrayDimFetch && $expression->dim instanceof Node\Scalar\String_ && ($base = $this->key($expression->var)) !== null) {
            return $base . '[' . json_encode($expression->dim->value, \JSON_THROW_ON_ERROR) . ']';
        }

        return null;
    }

    /**
     * @param Aliases $aliases
     *
     * @return Aliases
     */
    private function forget(array $aliases, Expr $target): array
    {
        $key = $this->key($target);
        while ($key === null && $target instanceof Expr\ArrayDimFetch) {
            $target = $target->var;
            $key = $this->key($target);
        }
        foreach (array_keys($aliases) as $known) {
            if ($key === $known || ($key !== null && str_starts_with($known, $key . '['))) {
                unset($aliases[$known]);
            }
        }

        return $aliases;
    }
}
