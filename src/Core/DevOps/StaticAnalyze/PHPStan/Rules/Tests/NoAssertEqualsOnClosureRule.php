<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<MethodCall>
 *
 * @internal
 */
#[Package('framework')]
class NoAssertEqualsOnClosureRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$scope->getClassReflection() || !TestRuleHelper::isTestClass($scope->getClassReflection())) {
            return [];
        }

        if (!$node->name instanceof Identifier || (string) $node->name !== 'assertEquals') {
            return [];
        }

        if (!(new ObjectType(TestCase::class))->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return [];
        }

        return self::checkArgs($node->getArgs(), $scope);
    }

    /**
     * @param array<Arg> $args
     *
     * @return list<RuleError>
     */
    public static function checkArgs(array $args, Scope $scope): array
    {
        $closureType = new ObjectType(\Closure::class);

        foreach (\array_slice($args, 0, 2) as $arg) {
            if ($closureType->isSuperTypeOf($scope->getType($arg->value))->yes()) {
                return [
                    RuleErrorBuilder::message(
                        'assertEquals() on Closure instances no longer does structural comparison since PHPUnit 12 — it falls back to identity (object hash), so two separately-constructed closures with identical behavior are unequal. Assert on the result of calling the closure instead.'
                    )
                        ->identifier('shopware.assertEqualsOnClosure')
                        ->build(),
                ];
            }
        }

        return [];
    }
}
