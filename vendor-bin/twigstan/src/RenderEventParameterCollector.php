<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPStan\Type\ErrorType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Routing\StorefrontRouteEventSubscriber;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @phpstan-import-type Listener from RenderEventListeners
 *
 * @phpstan-type Write = array{callback: string, kind: string, key: string, type: string, optional: bool, views: list<string>, file: string, line: int, reason: string}
 *
 * @implements Collector<Node\Expr\MethodCall, list<Write>>
 *
 * @internal
 */
#[Package('framework')]
final class RenderEventParameterCollector extends NodeVisitorAbstract implements Collector
{
    /**
     * @var array<string, string>
     */
    private array $parameters = [];

    public function __construct(private readonly string $manifestFile)
    {
        /** @var list<Listener> $listeners */
        $listeners = json_decode((new Filesystem())->readFile($manifestFile), true, flags: \JSON_THROW_ON_ERROR);
        foreach ($listeners as $listener) {
            $this->parameters[strtolower($listener['callback'])] = $listener['parameter'];
        }
    }

    public function getNodeType(): string
    {
        return Node\Expr\MethodCall::class;
    }

    public function enterNode(Node $node): ?Node
    {
        if (!$node instanceof Node\Stmt\ClassMethod || $node->stmts === null) {
            return null;
        }

        $finder = new NodeFinder();
        $canReturn = false;
        $priorViewGuard = false;
        foreach ($node->stmts as $statement) {
            $hasViewGuard = !$statement instanceof Node\Stmt\Expression && $finder->findFirst([$statement], static fn (Node $child): bool => $child instanceof Node\Expr\MethodCall && $child->name instanceof Node\Identifier && $child->name->name === 'getView') !== null;
            foreach ($finder->findInstanceOf([$statement], Node\Expr\MethodCall::class) as $call) {
                $call->setAttribute('renderEvent.optional', $canReturn || !$statement instanceof Node\Stmt\Expression || $statement->expr !== $call);
                $call->setAttribute('renderEvent.viewGuard', $priorViewGuard || $hasViewGuard);
            }
            $returns = $finder->findFirstInstanceOf([$statement], Node\Stmt\Return_::class) !== null;
            $priorViewGuard = $priorViewGuard || ($hasViewGuard && $returns);
            $canReturn = $canReturn || $returns;
        }

        return null;
    }

    /**
     * @param Scope&DependencyTracker $scope
     */
    public function processNode(Node $node, Scope $scope): ?array
    {
        $scope->trackFileDependency($this->manifestFile);
        $callback = $scope->getClassReflection()?->getName() . '::' . $scope->getFunctionName();
        $parameter = $this->parameters[strtolower($callback)] ?? null;
        if ($parameter === null || !$node->name instanceof Node\Identifier || $scope->isInAnonymousFunction()) {
            return null;
        }
        if ($callback === StorefrontRouteEventSubscriber::class . '::render') {
            return null;
        }

        $write = [
            'callback' => $callback,
            'kind' => 'parameter',
            'key' => '',
            'type' => '',
            'optional' => $node->getAttribute('renderEvent.optional', true) === true,
            'views' => [],
            'file' => $scope->getFile(),
            'line' => $node->getStartLine(),
            'reason' => '',
        ];
        $isEvent = (new ObjectType(StorefrontRenderEvent::class))->isSuperTypeOf($scope->getType($node->var))->yes();
        if (!$node->var instanceof Node\Expr\Variable || $node->var->name !== $parameter) {
            foreach ($node->getArgs() as $argument) {
                if (($argument->value instanceof Node\Expr\Variable && $argument->value->name === $parameter) || (new ObjectType(StorefrontRenderEvent::class))->isSuperTypeOf($scope->getType($argument->value))->yes()) {
                    return [[...$write, 'kind' => 'unresolved', 'reason' => 'Render event passed to a helper; its mutations are not inferred.']];
                }
            }
            if ($isEvent && $node->name->name === 'setParameter') {
                return [[...$write, 'kind' => 'unresolved', 'reason' => 'Render event mutation through an alias is not inferred.']];
            }

            return null;
        }
        if (!\in_array($node->name->name, ['setParameter', 'stopPropagation'], true)) {
            return null;
        }

        $views = $scope->getType(new Node\Expr\MethodCall($node->var, 'getView'))->getConstantStrings();
        if ($views === [] && $node->getAttribute('renderEvent.viewGuard', false) === true) {
            $unresolved = [...$write, 'kind' => 'unresolved', 'reason' => 'Cannot resolve the listener template restriction.'];

            return $node->name->name === 'stopPropagation'
                ? [$unresolved, [...$write, 'kind' => 'stop', 'optional' => true]]
                : [$unresolved];
        }
        $write['views'] = array_map(static fn ($view): string => $view->getValue(), $views);
        if ($node->name->name === 'stopPropagation') {
            return [[...$write, 'kind' => 'stop']];
        }

        $arguments = [];
        foreach ($node->getArgs() as $index => $argument) {
            $arguments[$argument->name !== null ? $argument->name->name : (['key', 'value'][$index] ?? '')] = $argument->value;
        }
        if (!isset($arguments['key'], $arguments['value'])) {
            return [[...$write, 'kind' => 'unresolved', 'reason' => 'Cannot resolve setParameter arguments.']];
        }
        $keys = $scope->getType($arguments['key'])->getConstantStrings();
        if ($keys === []) {
            return [[...$write, 'kind' => 'unresolved', 'reason' => 'Dynamic render parameter name.']];
        }
        $type = $scope->getType($arguments['value']);
        if ($type instanceof ErrorType || $type instanceof NeverType) {
            return [[...$write, 'kind' => 'unresolved', 'reason' => 'Cannot infer the render parameter value type.']];
        }

        return array_map(static fn ($key): array => [
            ...$write,
            'key' => $key->getValue(),
            'type' => (new Printer())->print($type->toPhpDocNode()),
            'optional' => $write['optional'] || \count($keys) > 1,
        ], $keys);
    }
}
