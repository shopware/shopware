<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayItem;
use PHPStan\Analyser\Scope;
use PHPStan\BetterReflection\Reflection\Adapter\FakeReflectionAttribute;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionAttribute;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('core')]
class RouteScopeRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @throws ShouldNotHappenException
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->getClassReflection()->is(AbstractController::class)) {
            return [];
        }

        $controllerReflection = $node->getClassReflection()->getNativeReflection();
        $classRouteAttr = $this->getRouteAnnotation($controllerReflection->getAttributes());

        $hasClassLevelScope = $classRouteAttr !== null && $this->definesRouteScopes($classRouteAttr);

        $errors = [];
        foreach ($controllerReflection->getMethods() as $method) {
            $methodRouteAttr = $this->getRouteAnnotation($method->getAttributes());
            if ($methodRouteAttr === null) {
                continue;
            }

            if ($this->resetsRouteScope($methodRouteAttr)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Method %s::%s() has no route scope defined. Please add a route scope to the method or the class.',
                    $method->getDeclaringClass()->getName(),
                    $method->getName()
                ))->line($method->getStartLine() ?: 0)->build();

                continue;
            }

            $routeScopeIsDefined = $hasClassLevelScope || $this->definesRouteScopes($methodRouteAttr);
            if (!$routeScopeIsDefined) {
                $errors[] = RuleErrorBuilder::message(
                    sprintf(
                        'Method %s::%s() has no route scope defined. Please add a route scope to the method or the class.',
                        $method->getDeclaringClass()->getName(),
                        $method->getName()
                    )
                )->line($method->getStartLine() ?: 0)->build();
            }
        }

        return $errors;
    }

    /**
     * @param list<ReflectionAttribute|FakeReflectionAttribute> $attributes
     */
    private function getRouteAnnotation(array $attributes): ?ReflectionAttribute
    {
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === Route::class) {
                \assert($attribute instanceof ReflectionAttribute);

                return $attribute;
            }
        }

        return null;
    }

    private function definesRouteScopes(ReflectionAttribute $routeAnnotation): bool
    {
        return $this->getRouteScopeCount($routeAnnotation) > 0;
    }

    private function resetsRouteScope(ReflectionAttribute $methodRouteAttr): bool
    {
        return $this->getRouteScopeCount($methodRouteAttr) === 0;
    }

    private function getRouteScopeCount(ReflectionAttribute $attribute): ?int
    {
        $defaults = $attribute->getArgumentsExpressions()['defaults'] ?? null;

        if (!$defaults instanceof Node\Expr\Array_) {
            return null;
        }

        foreach ($defaults->items as $item) {
            if ($item instanceof ArrayItem && $this->hasRouteScopeKey($item)) {
                \assert($item->value instanceof Node\Expr\Array_);

                return \count($item->value->items);
            }
        }

        return null;
    }

    private function hasRouteScopeKey(ArrayItem $item): bool
    {
        $key = $item->key;

        return $key instanceof Node\Scalar\String_ && $key->value === PlatformRequest::ATTRIBUTE_ROUTE_SCOPE;
    }
}
