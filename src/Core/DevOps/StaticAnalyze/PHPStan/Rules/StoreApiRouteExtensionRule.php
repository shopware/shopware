<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Reflection\AttributeReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ObjectType;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * @implements Rule<InClassMethodNode>
 */
#[Package('framework')]
class StoreApiRouteExtensionRule implements Rule
{
    /**
     * @param list<string> $legacyRouteMethods
     */
    public function __construct(private readonly array $legacyRouteMethods = [])
    {
    }

    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        if (str_starts_with($class->getName(), 'Shopware\\Tests\\')) {
            return [];
        }

        $method = $node->getMethodReflection();

        $routes = array_filter($method->getAttributes(), static fn (AttributeReflection $attribute) => $attribute->getName() === Route::class);
        if ($routes === []) {
            return [];
        }

        $classScope = $this->getRouteScope($class->getAttributes());
        $storeApiRoutes = array_filter($routes, fn (AttributeReflection $route) => \in_array(StoreApiRouteScope::ID, $this->getRouteScope([$route]) ?? $classScope ?? [], true));
        if ($storeApiRoutes === []) {
            return [];
        }

        $name = $class->getName() . '::' . $method->getName();
        if (\in_array($name, $this->legacyRouteMethods, true)) {
            return [];
        }

        $parent = $class->getParentClass();
        if ($class->hasNativeMethod('getDecorated')
            || ($parent !== null && $parent->isAbstract() && $parent->hasNativeMethod($method->getName()))
        ) {
            return [RuleErrorBuilder::message(\sprintf(
                'Store API route %s must use extension events instead of an abstract route/decorator contract.',
                $name,
            ))->identifier('shopware.storeApiRouteDecorator')->build()];
        }

        $statements = $node->getOriginalNode()->stmts ?? [];
        if (\count($statements) === 1
            && $statements[0] instanceof Return_
            && $statements[0]->expr instanceof MethodCall
            && $this->isExtensionDispatch($statements[0]->expr, $node, $scope)
        ) {
            return [];
        }

        return [RuleErrorBuilder::message(\sprintf(
            'Store API route %s must return ExtensionDispatcher::publish() with an Extension object and a private route-body method.',
            $name,
        ))->identifier('shopware.storeApiRouteExtension')->build()];
    }

    /**
     * @param list<AttributeReflection> $attributes
     *
     * @return list<string>|null
     */
    private function getRouteScope(array $attributes): ?array
    {
        foreach ($attributes as $attribute) {
            if ($attribute->getName() !== Route::class) {
                continue;
            }

            $defaults = $attribute->getArgumentTypes()['defaults'] ?? null;
            $key = new ConstantStringType(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE);
            if ($defaults === null || !$defaults->hasOffsetValueType($key)->yes()) {
                continue;
            }

            return array_map(
                static fn (ConstantStringType $type) => $type->getValue(),
                $defaults->getOffsetValueType($key)->getIterableValueType()->getConstantStrings(),
            );
        }

        return null;
    }

    private function isExtensionDispatch(MethodCall $call, InClassMethodNode $node, Scope $scope): bool
    {
        if (!$call->name instanceof Identifier
            || $call->name->toString() !== 'publish'
            || $call->isFirstClassCallable()
            || !(new ObjectType(ExtensionDispatcher::class))->isSuperTypeOf($scope->getType($call->var))->yes()
        ) {
            return false;
        }

        $arguments = [];
        foreach ($call->getArgs() as $position => $argument) {
            if ($argument->unpack) {
                return false;
            }

            $arguments[$argument->name?->toString() ?? ['name', 'extension', 'function'][$position] ?? $position] = $argument->value;
        }

        $extension = $arguments['extension'] ?? null;
        $callback = $arguments['function'] ?? null;

        if ($extension === null
            || !(new ObjectType(Extension::class))->isSuperTypeOf($scope->getType($extension))->yes()
            || !$callback instanceof MethodCall
            || !$callback->isFirstClassCallable()
            || !$callback->var instanceof Variable
            || $callback->var->name !== 'this'
            || !$callback->name instanceof Identifier
        ) {
            return false;
        }

        $class = $node->getClassReflection();
        $method = $callback->name->toString();

        return $class->hasNativeMethod($method) && $class->getNativeMethod($method)->isPrivate();
    }
}
