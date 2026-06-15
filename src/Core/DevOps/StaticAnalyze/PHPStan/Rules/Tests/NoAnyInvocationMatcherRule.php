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
use Shopware\Core\Framework\Log\Package;

/**
 * Bans the PHPUnit `any()` invocation matcher: `->expects($this->any())` (also `static::`/`self::any()`).
 *
 * `any()` places no constraint on the invocation count, so `->expects($this->any())->method('foo')`
 * behaves identically to a bare `->method('foo')` — the matcher is pure noise. The meaningful matchers
 * (`once()`, `never()`, `exactly()`, `atLeastOnce()`, …) actually assert a call count and are NOT flagged.
 *
 * Detection is structural — it keys off the `expects(<any()>)` shape rather than the receiver type — so it
 * fires inside test classes, in `src/**\/Test` support traits, and anywhere the idiom appears, without
 * relying on `$this` resolving to a TestCase subtype (which it does not inside a trait).
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

        return [
            RuleErrorBuilder::message(self::ERROR_REDUNDANT)
                ->identifier('shopware.phpunitAnyMatcher')
                ->build(),
        ];
    }
}
