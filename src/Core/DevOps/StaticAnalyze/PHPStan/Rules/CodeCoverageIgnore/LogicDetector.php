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
     * Branching and error-path constructs. Calls, instantiation, arithmetic,
     * and coalesce are intentionally absent — they're not branching by themselves,
     * and the called code has its own coverage story.
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
    ];

    private function __construct()
    {
    }

    public static function methodContainsLogic(ClassMethod $method): bool
    {
        if ($method->stmts === null) {
            return false;
        }

        $hit = (new NodeFinder())->findFirst($method->stmts, static function (Node $node): bool {
            foreach (self::LOGIC_NODE_TYPES as $type) {
                if ($node instanceof $type) {
                    return true;
                }
            }

            return false;
        });

        return $hit !== null;
    }
}
