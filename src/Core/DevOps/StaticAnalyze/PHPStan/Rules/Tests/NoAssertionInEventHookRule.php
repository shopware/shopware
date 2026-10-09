<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Parser\Parser;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;

/**
 * An event hook runs inside dispatch(), so the code under test can catch the failed assertion and keep the test
 * green: SendMailAction logs every exception around MailSentEvent, flow actions and message handlers do the same.
 * The hook captures the event, the test asserts after the code under test ran. Covers the closure given to on()
 * and the listener methods of the subscriber given to subscribe(), wherever that subscriber class is declared.
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

    private const SUBSCRIBE_METHOD = 'subscribe';

    public function __construct(private readonly Parser $parser)
    {
    }

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
        if (!$node->name instanceof Identifier) {
            return [];
        }

        $method = $node->name->toString();
        if ($method !== self::HOOK_METHOD && $method !== self::SUBSCRIBE_METHOD) {
            return [];
        }

        if (!(new ObjectType(EventHookDispatcher::class))->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return [];
        }

        if ($method === self::HOOK_METHOD) {
            $hook = $node->getArgs()[1]->value ?? null;
            if (!$hook instanceof Closure && !$hook instanceof ArrowFunction) {
                return [];
            }

            return $this->assertionsIn($hook, null);
        }

        $subscriber = $node->getArgs()[0]->value ?? null;
        if ($subscriber === null) {
            return [];
        }

        // an anonymous class written inline is already in hand
        if ($subscriber instanceof New_ && $subscriber->class instanceof Class_) {
            return $this->assertionsIn($subscriber->class, null);
        }

        $errors = [];
        foreach ($scope->getType($subscriber)->getObjectClassReflections() as $classReflection) {
            $class = $this->findClassNode($classReflection);
            if ($class === null) {
                continue;
            }

            $file = $classReflection->getFileName();
            $errors = [...$errors, ...$this->assertionsIn($class, $file === $scope->getFile() ? null : $file)];
        }

        return $errors;
    }

    /**
     * @return list<RuleError>
     */
    private function assertionsIn(Node $hook, ?string $file): array
    {
        $errors = [];
        foreach ((new NodeFinder())->find($hook, self::isAssertion(...)) as $assertion) {
            \assert($assertion instanceof StaticCall || $assertion instanceof MethodCall);
            \assert($assertion->name instanceof Identifier);

            $error = RuleErrorBuilder::message(\sprintf(self::ERROR, $assertion->name->toString()))
                ->identifier('shopware.assertionInEventHook')
                ->line($assertion->getStartLine());
            if ($file !== null) {
                $error->file($file);
            }

            $errors[] = $error->build();
        }

        return $errors;
    }

    /**
     * The declaration of a subscriber class, parsed from its own file; an anonymous class is matched by its line.
     */
    private function findClassNode(ClassReflection $classReflection): ?Class_
    {
        $file = $classReflection->getFileName();
        if ($file === null) {
            return null;
        }

        $expectedLine = $classReflection->isAnonymous() ? $classReflection->getNativeReflection()->getStartLine() : null;
        $expectedName = $classReflection->isAnonymous() ? null : $classReflection->getNativeReflection()->getShortName();

        foreach ((new NodeFinder())->findInstanceOf($this->parser->parseFile($file), Class_::class) as $class) {
            if ($expectedLine !== null && $class->getStartLine() === $expectedLine) {
                return $class;
            }

            if ($expectedName !== null && $class->name?->toString() === $expectedName) {
                return $class;
            }
        }

        return null;
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
