<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Framework\Log\Package;

/**
 * Bans the PHPUnit `any()` invocation matcher: `->expects($this->any())` (also `static::`/`self::any()`).
 *
 * `any()` places no constraint on the invocation count, so `->expects($this->any())->method('foo')`
 * behaves identically to a bare `->method('foo')` — the matcher is pure noise. The meaningful matchers
 * (`once()`, `never()`, `exactly()`, `atLeastOnce()`, …) actually assert a call count and are NOT flagged.
 *
 * Detection requires the `expects(<any()>)` shape AND that the object `expects()` is called on is a
 * PHPUnit `MockObject`. The mock type is inferred from `createMock()` / the parameter declaration, so it
 * still resolves inside `src/**\/Test` support traits (where `$this` is not a TestCase subtype) — unlike
 * checking the `expects()` caller against `TestCase`. This keeps the rule from flagging unrelated fluent
 * APIs that happen to expose `expects()` / `any()`.
 *
 * @implements Rule<MethodCall>
 *
 * @internal
 */
#[Package('framework')]
class NoAnyInvocationMatcherRule implements Rule
{
    public const ERROR_REDUNDANT = 'The any() invocation matcher is redundant: expects($this->any()) places no constraint on the call count, so it is equivalent to omitting expects() entirely. Drop it and call ->method()/->willReturn() directly on the mock.';

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
        if (!$node->name instanceof Identifier || $node->name->name !== 'expects') {
            return [];
        }

        $args = $node->getArgs();
        if (\count($args) !== 1) {
            return [];
        }

        $matcher = $args[0]->value;

        // expects($this->any()) → MethodCall; expects(static::any()/self::any()) → StaticCall.
        $isAny = ($matcher instanceof MethodCall || $matcher instanceof StaticCall)
            && $matcher->name instanceof Identifier
            && $matcher->name->name === 'any'
            && \count($matcher->getArgs()) === 0;

        if (!$isAny) {
            return [];
        }

        // Ensure this is PHPUnit's mock API, not an unrelated fluent method sharing the expects()/any()
        // names. Keys off the mock's own type (MockObject&X from createMock), so it still resolves
        // inside support traits where $this is not a TestCase subtype.
        if (!(new ObjectType(MockObject::class))->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::ERROR_REDUNDANT)
                ->identifier('shopware.phpunitAnyMatcher')
                ->build(),
        ];
    }
}
