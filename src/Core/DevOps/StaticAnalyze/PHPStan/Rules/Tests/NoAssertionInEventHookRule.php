<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;

/**
 * An event hook runs inside dispatch(), so the code under test can catch the failed assertion and keep the test
 * green: SendMailAction logs every exception around MailSentEvent, flow actions and message handlers do the same.
 * The hook captures the event, the test asserts after the code under test ran.
 *
 * @implements Rule<MethodCall>
 *
 * @internal
 */
#[Package('framework')]
class NoAssertionInEventHookRule implements Rule
{
    public const ERROR = 'Do not call %s() inside an event hook: the hook runs inside dispatch(), where the code under test can catch the failure and the test stays green. Capture the event in the hook and assert after the code under test ran.';

    private const HOOK_METHOD = 'on';

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
        if (!$node->name instanceof Identifier || $node->name->toString() !== self::HOOK_METHOD) {
            return [];
        }

        $hook = $node->getArgs()[1]->value ?? null;
        if (!$hook instanceof Closure && !$hook instanceof ArrowFunction) {
            return [];
        }

        if (!(new ObjectType(EventHookDispatcher::class))->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return [];
        }

        $errors = [];
        foreach ((new NodeFinder())->find($hook, self::isAssertion(...)) as $assertion) {
            \assert($assertion instanceof StaticCall || $assertion instanceof MethodCall);
            \assert($assertion->name instanceof Identifier);

            $errors[] = RuleErrorBuilder::message(\sprintf(self::ERROR, $assertion->name->toString()))
                ->identifier('shopware.assertionInEventHook')
                ->line($assertion->getStartLine())
                ->build();
        }

        return $errors;
    }

    /**
     * static::assert*(), self::assert*(), $this->assert*() and the fail() variants, as PHPUnit tests write them.
     */
    private static function isAssertion(Node $node): bool
    {
        if (!$node instanceof StaticCall && !$node instanceof MethodCall) {
            return false;
        }

        if (!$node->name instanceof Identifier) {
            return false;
        }

        $method = $node->name->toString();

        return \str_starts_with($method, 'assert') || $method === 'fail';
    }
}
