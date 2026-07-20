<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Symfony\ServiceMap;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Routing\Annotation\Route as RouteAnnotation;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * @implements Rule<InClassNode>
 */
#[Package('framework')]
class NoRouteOverrideInDecoratorsRule implements Rule
{
    public function __construct(private readonly ServiceMap $serviceMap)
    {
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof InClassNode) {
            return [];
        }

        $reflection = $node->getClassReflection();

        if (!$this->isServiceDecorator($reflection->getName())) {
            return [];
        }

        if (!$this->definesRouteAttribute($reflection)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                \sprintf(
                    'Service "%s" is a decorator but overrides @Route attributes (class or method-level). Decorators must not override or define routes, otherwise changes to the core route definition don\'t have any affect; only the core route should define the @Route attribute.',
                    $reflection->getName(),
                )
            )
            ->identifier('shopware.routeDecorator')
            ->build(),
        ];
    }

    public function definesRouteAttribute(ClassReflection $reflection): bool
    {
        $native = $reflection->getNativeReflection();
        // check class level attributes for Route
        if ($native->getAttributes(Route::class) !== []) {
            return true;
        }

        /** @phpstan-ignore classConstant.deprecatedClass (Only there to check for deprecated usage, if can be removed when class is removed) */
        if ($native->getAttributes(RouteAnnotation::class) !== []) {
            return true;
        }

        // check method level attributes for Route
        foreach ($native->getMethods() as $method) {
            // only consider methods declared in this class
            if ($method->getDeclaringClass()->getName() !== $native->getName()) {
                continue;
            }

            if ($method->getAttributes(Route::class) !== []) {
                return true;
            }

            /** @phpstan-ignore classConstant.deprecatedClass (Only there to check for deprecated usage, if can be removed when class is removed) */
            if ($method->getAttributes(RouteAnnotation::class) !== []) {
                return true;
            }
        }

        return false;
    }

    private function isServiceDecorator(string $className): bool
    {
        $service = $this->serviceMap->getService($className);

        if ($service === null) {
            return false;
        }

        if ($service->getTags() === []) {
            return false;
        }

        $decorates = null;

        foreach ($service->getTags() as $tag) {
            /** @phpstan-ignore phpstanApi.method */
            if ($tag->getName() === 'container.decorator') {
                /** @phpstan-ignore phpstanApi.method */
                $decorates = $tag->getAttributes()['id'] ?? null;

                break;
            }
        }

        if ($decorates === null) {
            return false;
        }

        return true;
    }
}
