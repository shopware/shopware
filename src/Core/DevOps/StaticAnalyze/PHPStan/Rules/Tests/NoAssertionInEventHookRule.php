<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
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
 * The hook captures the event, the test asserts after the code under test ran. Covers the closure given to on(),
 * the helpers it calls on the test class, its traits and its ancestors, and the methods of the subscriber given to
 * subscribe(), wherever that subscriber class is declared.
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

            $owner = $scope->getClassReflection();

            return $this->assertionsIn($hook, $scope->getFile(), $scope->getFile(), $owner === null ? [] : $this->ownerChain($owner));
        }

        $subscriber = $node->getArgs()[0]->value ?? null;
        if ($subscriber === null) {
            return [];
        }

        // an anonymous class written inline is already in hand
        if ($subscriber instanceof New_ && $subscriber->class instanceof Class_) {
            return $this->assertionsIn($subscriber->class, $scope->getFile(), $scope->getFile(), []);
        }

        $errors = [];
        foreach ($scope->getType($subscriber)->getObjectClassReflections() as $classReflection) {
            $class = $this->findClassNode($classReflection);
            if ($class === null) {
                continue;
            }

            $errors = [...$errors, ...$this->assertionsIn($class['node'], $class['file'], $scope->getFile(), [])];
        }

        return $errors;
    }

    /**
     * Assertions in the hook itself and, for a closure, in the methods it calls through $this, self or static,
     * resolved along the owner chain and followed transitively. A subscriber class is scanned whole, so no chain.
     *
     * @param list<array{node: ClassLike, file: string}> $chain the test class, its traits and its ancestors, in that order
     * @param array<string, true> $visited
     *
     * @return list<RuleError>
     */
    private function assertionsIn(Node $hook, string $hookFile, string $currentFile, array $chain, array &$visited = []): array
    {
        $errors = [];
        foreach ((new NodeFinder())->find($hook, self::isAssertion(...)) as $assertion) {
            \assert($assertion instanceof StaticCall || $assertion instanceof MethodCall);
            \assert($assertion->name instanceof Identifier);

            $error = RuleErrorBuilder::message(\sprintf(self::ERROR, $assertion->name->toString()))
                ->identifier('shopware.assertionInEventHook')
                ->line($assertion->getStartLine());
            if ($hookFile !== $currentFile) {
                $error->file($hookFile);
            }

            $errors[] = $error->build();
        }

        foreach ((new NodeFinder())->find($hook, self::isOwnMethodCall(...)) as $call) {
            \assert(($call instanceof StaticCall || $call instanceof MethodCall) && $call->name instanceof Identifier);
            // an assertion is reported above, not followed into PHPUnit
            $name = $call->name->toString();
            if (isset($visited[$name]) || self::isAssertion($call)) {
                continue;
            }

            foreach ($chain as $owner) {
                $helper = $owner['node']->getMethod($name);
                if ($helper === null) {
                    continue;
                }

                $visited[$name] = true;
                $errors = [...$errors, ...$this->assertionsIn($helper, $owner['file'], $currentFile, $chain, $visited)];

                break;
            }
        }

        return $errors;
    }

    /**
     * The declarations a $this call can resolve to: the class, the traits it uses, then each ancestor with its
     * traits, the way PHP resolves the method.
     *
     * @return list<array{node: ClassLike, file: string}>
     */
    private function ownerChain(ClassReflection $classReflection): array
    {
        $chain = [];
        foreach ([$classReflection, ...array_values($classReflection->getParents())] as $class) {
            foreach ([$class, ...array_values($class->getTraits(true))] as $declaration) {
                $node = $this->findClassNode($declaration);
                if ($node !== null) {
                    $chain[] = $node;
                }
            }
        }

        return $chain;
    }

    /**
     * The declaration of a class or trait, parsed from its own file; an anonymous class is matched by its line.
     * Vendor code is never parsed: PHPUnit's own classes sit on every test's chain.
     *
     * @return array{node: ClassLike, file: string}|null
     */
    private function findClassNode(ClassReflection $classReflection): ?array
    {
        $file = $classReflection->getFileName();
        if ($file === null || str_contains($file, \DIRECTORY_SEPARATOR . 'vendor' . \DIRECTORY_SEPARATOR)) {
            return null;
        }

        $expectedLine = $classReflection->isAnonymous() ? $classReflection->getNativeReflection()->getStartLine() : null;
        $expectedName = $classReflection->isAnonymous() ? null : $classReflection->getNativeReflection()->getShortName();

        foreach ((new NodeFinder())->findInstanceOf($this->parser->parseFile($file), ClassLike::class) as $class) {
            if ($expectedLine !== null && $class instanceof Class_ && $class->getStartLine() === $expectedLine) {
                return ['node' => $class, 'file' => $file];
            }

            if ($expectedName !== null && $class->name?->toString() === $expectedName) {
                return ['node' => $class, 'file' => $file];
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

    /**
     * $this->helper(), self::helper() and static::helper(): a method of the test class that owns the hook.
     */
    private static function isOwnMethodCall(Node $node): bool
    {
        if ($node instanceof MethodCall) {
            return $node->var instanceof Variable && $node->var->name === 'this' && $node->name instanceof Identifier;
        }

        return $node instanceof StaticCall
            && $node->class instanceof Name
            && \in_array($node->class->toLowerString(), ['self', 'static'], true)
            && $node->name instanceof Identifier;
    }
}
