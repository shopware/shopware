<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Symfony 8.2 compiles the shared `event_dispatcher`. Adding or removing a listener on it at runtime is
 * deprecated there and makes it fall back to the regular dispatcher, so a test must not do it to observe an
 * event. A dispatcher the test builds itself (`new EventDispatcher()`) and test doubles are fine. Enforcement
 * is limited to the namespaces in {@see Configuration}, so the existing tests can move namespace by namespace.
 *
 * @implements Rule<MethodCall>
 *
 * @internal
 */
#[Package('framework')]
class NoRuntimeListenerOnSharedEventDispatcherRule implements Rule
{
    public const ERROR = 'Do not call %s() on the shared event dispatcher: Symfony 8.2 compiles it and deprecates runtime listener changes. Hook the event with EventHookDispatcher::fromContainer()->on(), or dispatch through a dispatcher the test builds itself.';

    public const ERROR_HELPER = 'Do not pass the shared event dispatcher to addEventListener(): it adds a runtime listener, which Symfony 8.2 deprecates for its compiled dispatcher. Hook the event with EventHookDispatcher::fromContainer()->on() instead.';

    private const METHODS = ['addListener', 'addSubscriber', 'removeListener', 'removeSubscriber'];

    /**
     * The EventDispatcherBehaviour helper, which adds a listener to the dispatcher it is given.
     */
    private const HELPER = 'addEventListener';

    /**
     * @var list<string>
     */
    private readonly array $enabledNamespaces;

    public function __construct(Configuration $configuration)
    {
        $this->enabledNamespaces = $configuration->getRuntimeListenerOnSharedEventDispatcherEnabledNamespaces();
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
        if (!$node->name instanceof Identifier || !$this->isEnabledNamespace($scope->getNamespace() ?? '')) {
            return [];
        }

        $method = $node->name->toString();
        if (\in_array($method, self::METHODS, true)) {
            $dispatcher = $node->var;
            $message = \sprintf(self::ERROR, $method);
        } elseif ($method === self::HELPER && isset($node->getArgs()[0])) {
            $dispatcher = $node->getArgs()[0]->value;
            $message = self::ERROR_HELPER;
        } else {
            return [];
        }

        if (!$this->isSharedDispatcher($dispatcher, $scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message($message)
                ->identifier('shopware.runtimeListenerOnSharedEventDispatcher')
                ->build(),
        ];
    }

    private function isSharedDispatcher(Expr $dispatcher, Scope $scope): bool
    {
        $type = $scope->getType($dispatcher);
        if (!(new ObjectType(EventDispatcherInterface::class))->isSuperTypeOf($type)->yes()) {
            return false;
        }

        // a dispatcher built by the test, or a double of one, is not the shared service; the native type ignores
        // a `@var EventDispatcher` annotation on the container's dispatcher
        return !(new ObjectType(EventDispatcher::class))->isSuperTypeOf($scope->getNativeType($dispatcher))->yes()
            && !(new ObjectType(MockObject::class))->isSuperTypeOf($type)->yes();
    }

    private function isEnabledNamespace(string $namespace): bool
    {
        foreach ($this->enabledNamespaces as $enabled) {
            if (\str_starts_with($namespace . '\\', $enabled)) {
                return true;
            }
        }

        return false;
    }
}
