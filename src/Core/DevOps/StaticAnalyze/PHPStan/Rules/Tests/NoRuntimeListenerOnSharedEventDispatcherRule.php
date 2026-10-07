<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
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
    public const ERROR = 'Do not call %s() on the shared event dispatcher: Symfony 8.2 compiles it and deprecates runtime listener changes. Hook the event with EventHookBehaviour::onEvent(), or dispatch through a dispatcher the test builds itself.';

    private const METHODS = ['addListener', 'addSubscriber', 'removeListener', 'removeSubscriber'];

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
        if (!$node->name instanceof Identifier || !\in_array($node->name->toString(), self::METHODS, true)) {
            return [];
        }

        if (!$this->isEnabledNamespace($scope->getNamespace() ?? '')) {
            return [];
        }

        $type = $scope->getType($node->var);
        if (!(new ObjectType(EventDispatcherInterface::class))->isSuperTypeOf($type)->yes()) {
            return [];
        }

        // a dispatcher built by the test, or a double of one, is not the shared service; the native type ignores
        // a `@var EventDispatcher` annotation on the container's dispatcher
        if ((new ObjectType(EventDispatcher::class))->isSuperTypeOf($scope->getNativeType($node->var))->yes()
            || (new ObjectType(MockObject::class))->isSuperTypeOf($type)->yes()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(self::ERROR, $node->name->toString()))
                ->identifier('shopware.runtimeListenerOnSharedEventDispatcher')
                ->build(),
        ];
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
