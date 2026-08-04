<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * Flags `assertEquals()` comparisons where an operand is a Closure, for both call shapes:
 * `static::assertEquals(...)` (StaticCall) and `$test->assertEquals(...)` (MethodCall). Registering on
 * their common parent CallLike lets one rule cover both — PHPStan dispatches a node to rules registered
 * for its class or any ancestor.
 *
 * @implements Rule<CallLike>
 *
 * @internal
 */
#[Package('framework')]
class NoAssertEqualsOnClosureRule implements Rule
{
    public const ERROR_MESSAGE = 'assertEquals() on Closure instances no longer does structural comparison since PHPUnit 12 — it falls back to identity (object hash), so two separately-constructed closures with identical behavior are unequal. Assert on the result of calling the closure instead.';

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /**
     * @param CallLike $node
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall && !$node instanceof StaticCall) {
            return [];
        }

        if (!$scope->getClassReflection() || !TestRuleHelper::isTestClass($scope->getClassReflection())) {
            return [];
        }

        if (!$node->name instanceof Identifier || (string) $node->name !== 'assertEquals') {
            return [];
        }

        // For an instance call only fire when the receiver is a TestCase; a static call (static::/self::/
        // Class::) carries no receiver and is already scoped by the enclosing-test-class check above.
        if ($node instanceof MethodCall
            && !(new ObjectType(TestCase::class))->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return [];
        }

        $closureType = new ObjectType(\Closure::class);

        foreach (\array_slice($node->getArgs(), 0, 2) as $arg) {
            $argType = $scope->getType($arg->value);

            // `never` is the bottom type and therefore a subtype of every type,
            // so isSuperTypeOf() below would report it as a Closure. It is never
            // an actual closure — it signals unreachable/contradictory narrowing
            // (e.g. assertNull() then assertNotNull() on the same accessor) — so skip it.
            if ($argType instanceof NeverType) {
                continue;
            }

            if ($closureType->isSuperTypeOf($argType)->yes()) {
                return [
                    RuleErrorBuilder::message(self::ERROR_MESSAGE)
                        ->identifier('shopware.assertEqualsOnClosure')
                        ->build(),
                ];
            }
        }

        return [];
    }
}
