<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class LogicDetector
{
    /**
     * Branching and error-path constructs. Calls, instantiation, arithmetic,
     * and coalesce are intentionally absent — they're not branching by themselves,
     * and the called code has its own coverage story.
     *
     * @var list<class-string<Node>>
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
        Stmt\Throw_::class,
        Expr\Throw_::class,
        Expr\Ternary::class,
    ];

    private function __construct()
    {
    }

    public static function methodContainsLogic(ClassMethod $method): bool
    {
        if ($method->stmts === null) {
            return false;
        }

        foreach ($method->stmts as $stmt) {
            if (self::nodeContainsLogic($stmt)) {
                return true;
            }
        }

        return false;
    }

    private static function nodeContainsLogic(Node $node): bool
    {
        foreach (self::LOGIC_NODE_TYPES as $type) {
            if ($node instanceof $type) {
                return true;
            }
        }

        foreach ($node->getSubNodeNames() as $name) {
            $value = $node->{$name};
            if ($value instanceof Node) {
                if (self::nodeContainsLogic($value)) {
                    return true;
                }
            } elseif (\is_array($value)) {
                foreach ($value as $item) {
                    if ($item instanceof Node && self::nodeContainsLogic($item)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
