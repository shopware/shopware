<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeTraverser;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<ClassMethod>
 *
 * @internal
 */
#[Package('framework')]
class UnusedLocalVariableRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->getStmts() === null) {
            return []; // Abstract methods / no body
        }

        // Parameters to ignore
        $ignore = [];
        foreach ($node->params as $param) {
            if ($param->var instanceof Variable && \is_string($param->var->name)) {
                $ignore[$param->var->name] = true;
            }
        }

        $traverser = new NodeTraverser();
        $visitor = new VariableUsageCollector($ignore);
        $traverser->addVisitor($visitor);

        if ($node->stmts === null) {
            return [];
        }

        $traverser->traverse($node->stmts);

        $unused = array_diff($visitor->getDeclared(), $visitor->getUsed());

        if (!empty($unused)) {
            return [
                RuleErrorBuilder::message(\sprintf(
                    'Method `%s` has unused local variables: %s',
                    $node->name->toString(),
                    implode(', ', $unused)
                ))->identifier('shopware.unusedLocalVariable')->build(),
            ];
        }

        return [];
    }
}
