<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Foreach_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements Rule<Foreach_>
 */
#[Package('framework')]
class LoopInvariantAssignmentRule implements Rule
{
    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return Foreach_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Foreach_) {
            return [];
        }

        // collect loop variables: foreach ($iterable as $key => $value)
        $loopVars = [];

        if ($node->valueVar instanceof Node\Expr\List_) {
            foreach ($node->valueVar->items as $item) {
                if ($item instanceof Node\ArrayItem && $item->value instanceof Variable && \is_string($item->value->name)) {
                    $loopVars[$item->value->name] = 1;
                }
            }
        }

        if ($node->valueVar instanceof Variable && \is_string($node->valueVar->name)) {
            $loopVars[$node->valueVar->name] = 1;
        }
        if ($node->keyVar instanceof Variable && \is_string($node->keyVar->name)) {
            $loopVars[$node->keyVar->name] = 1;
        }

        foreach ($node->stmts as $stmt) {
            $stmtVariables = $this->collectLoopVariables($stmt);

            foreach ($stmtVariables as $varName => $count) {
                if (isset($loopVars[$varName])) {
                    $loopVars[$varName] += $count;
                } else {
                    $loopVars[$varName] = $count;
                }
            }
        }

        foreach ($node->stmts as $stmt) {
            if (!$stmt instanceof Node\Stmt\Expression) {
                continue;
            }

            $expr = $stmt->expr;

            if (!$expr instanceof Assign && !$expr instanceof Node\Expr\AssignRef) {
                continue;
            }

            if ($expr->expr instanceof Node\Expr\Clone_) {
                continue; // cloning is fine
            }

            if (!$expr->var instanceof Variable || !\is_string($expr->var->name)) {
                continue;
            }

            $exprType = $scope->getType($expr->expr);

            if ($exprType->isScalar()->yes() || $exprType->isNull()->yes() || $exprType->isArray()->yes()) {
                // if assign to a scalar, null or array, we can skip it because it doesn't cost much performance and could potentially a loop's tmp variable
                continue;
            }

            // Skip if the variable is a mutable class instance
            if ($expr->expr instanceof Node\Expr\New_) {
                if (!$expr->expr->class instanceof Node\Name) {
                    continue;
                }

                if ($this->isMutableClass($expr->expr->class->name)) {
                    continue;
                }
            }

            /** @phpstan-ignore phpstanApi.instanceofType */
            if ($exprType->isObject()->yes() && $exprType instanceof ObjectType && $this->isMutableClass($exprType->getClassName())) {
                continue;
            }

            $varName = $expr->var->name;

            if (isset($loopVars[$varName]) && $loopVars[$varName] > 1) {
                // if the variable is reassigned multiple times, we can skip it
                continue;
            }

            // collect all variables used in the RHS (assignment expr)
            $usedVars = $this->collectVariables($expr->expr);

            if ($expr->expr instanceof Variable && \is_string($expr->expr->name)) {
                $usedVars[] = $expr->expr->name;
            }

            // check if it depends on any loop variables
            if (!array_intersect(array_keys($loopVars), $usedVars)) {
                return [
                    RuleErrorBuilder::message(
                        \sprintf(
                            'Variable $%s is reassigned inside foreach loop but does not depend on loop variables — consider moving it outside.',
                            $varName
                        )
                    )->identifier('shopware.loopVariantCouldBeHoisted')->build(),
                ];
            }
        }

        return [];
    }

    /**
     * Recursively collect variable names from an expression.
     *
     * @return string[]
     */
    private function collectVariables(Node $expr): array
    {
        $vars = [];
        foreach ($expr->getSubNodeNames() as $name) {
            if (!property_exists($expr, $name)) {
                continue;
            }
            /** @phpstan-ignore symplify.noDynamicName */
            $sub = $expr->$name;
            if ($sub instanceof Variable && \is_string($sub->name)) {
                $vars[] = $sub->name;
            } elseif (\is_array($sub)) {
                foreach ($sub as $s) {
                    if ($s instanceof Node) {
                        $vars = array_merge($vars, $this->collectVariables($s));
                    }
                }
            } elseif ($sub instanceof Node) {
                $vars = array_merge($vars, $this->collectVariables($sub));
            }
        }

        return $vars;
    }

    /**
     * Recursively collect variable names from an expression.
     *
     * @return string[]
     */
    private function collectLoopVariables(Node\Stmt $stmt): array
    {
        $loopVars = [];

        if (property_exists($stmt, 'stmts') && !empty($stmt->stmts)) {
            foreach ($stmt->stmts as $subStmt) {
                if (!$subStmt instanceof Node\Stmt) {
                    break;
                }

                $subStmtVariables = $this->collectLoopVariables($subStmt);
                foreach ($subStmtVariables as $varName => $count) {
                    if (isset($loopVars[$varName])) {
                        $loopVars[$varName] += $count;
                    } else {
                        $loopVars[$varName] = $count;
                    }
                }
            }
        }

        if (!property_exists($stmt, 'expr') || !$stmt->expr instanceof Node\Expr) {
            return $loopVars;
        }

        $expr = $stmt->expr;

        if (!$expr instanceof Assign && !$expr instanceof Node\Expr\AssignRef) {
            return $loopVars;
        }

        if ($expr->var instanceof Node\Expr\List_) {
            foreach ($expr->var->items as $item) {
                if ($item instanceof Node\ArrayItem && $item->value instanceof Variable && \is_string($item->value->name)) {
                    $loopVars[$item->value->name] = isset($loopVars[$item->value->name]) ? $loopVars[$item->value->name] + 1 : 1;
                }
            }
        }

        if (!$expr->var instanceof Variable || !\is_string($expr->var->name)) {
            return $loopVars;
        }

        $loopVars[$expr->var->name] = isset($loopVars[$expr->var->name]) ? $loopVars[$expr->var->name] + 1 : 1;

        return $loopVars;
    }

    private function isMutableClass(string $fqcn): bool
    {
        if (!$this->reflectionProvider->hasClass($fqcn)) {
            return false;
        }

        $classReflection = $this->reflectionProvider->getClass($fqcn);

        // 🔑 Early return if whole class is readonly
        if ($classReflection->isReadOnly()) {
            return false;
        }

        // Check setters (including parent + traits)
        foreach ($classReflection->getNativeReflection()->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();
            if (str_starts_with($methodName, 'add') || str_starts_with($methodName, 'set')) {
                return true;
            }
        }

        // Check mutable properties
        foreach ($classReflection->getNativeReflection()->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isReadOnly()) {
                return true;
            }
        }

        return false;
    }
}
