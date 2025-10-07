<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use Doctrine\DBAL\Query\QueryBuilder;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements Rule<MethodCall>
 */
#[Package('framework')]
class NoUpdatesInExecuteQueryRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall
            || !($node->name instanceof Identifier)
            || $node->name->toString() !== 'executeQuery'
        ) {
            return [];
        }

        $errors = [];

        $varType = $scope->getType($node->var);
        if (\in_array(QueryBuilder::class, $varType->getObjectClassNames(), true)) {
            $current = $node->var;
            $hasWriteCall = false;

            while ($current instanceof MethodCall) {
                if ($current->name instanceof Identifier) {
                    $method = strtolower($current->name->toString());
                    if (\in_array($method, ['update', 'insert', 'delete'], true)) {
                        $hasWriteCall = true;
                        break;
                    }
                }
                $current = $current->var;
            }

            if ($hasWriteCall) {
                $errors[] = RuleErrorBuilder::message(
                    'Calling executeQuery() on a Doctrine QueryBuilder that performs update/insert/delete is forbidden. Use executeStatement() instead.'
                )->identifier('shopware.noExecuteQuery')->build();
            }

            return $errors;
        }

        if (!empty($node->args)) {
            $firstArg = $node->args[0];

            if ($firstArg instanceof Node\Arg && $firstArg->value instanceof Node\Scalar\String_) {
                $sql = strtoupper($firstArg->value->value);
                if (preg_match('/\b(UPDATE|DELETE|INSERT|REPLACE|DROP|TRUNCATE)\b/', $sql)) {
                    $errors[] = RuleErrorBuilder::message(
                        'executeQuery() with raw SQL containing write operations (UPDATE/DELETE/INSERT/...) is forbidden. Use executeStatement() instead.'
                    )->identifier('shopware.noExecuteQuery')->build();
                }
            } elseif ($firstArg instanceof Node\Arg && $firstArg->value instanceof Node\Expr\Variable) {
                $variableName = $firstArg->value->name;

                $variableType = $scope->getType($firstArg->value);
                if ($variableType->isString()->yes()) {
                    $constantStrings = $variableType->getConstantStrings();
                    foreach ($constantStrings as $constantString) {
                        $sql = strtoupper($constantString->getValue());
                        if (preg_match('/\b(UPDATE|DELETE|INSERT|REPLACE|DROP|TRUNCATE)\b/', $sql)) {
                            $errors[] = RuleErrorBuilder::message(
                                \sprintf(
                                    'Passing a variable ($%s) containing SQL with write operations to executeQuery() is forbidden. Use executeStatement() instead.',
                                    \is_string($variableName) ? $variableName : 'unknown'
                                )
                            )->identifier('shopware.noExecuteQueryVariable')->build();
                            break;
                        }
                    }
                }
            }
        }

        return $errors;
    }
}
