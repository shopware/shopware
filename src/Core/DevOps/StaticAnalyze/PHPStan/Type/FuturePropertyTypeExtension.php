<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Type;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ExpressionTypeResolverExtension;
use PHPStan\Type\Type;
use Shopware\Core\Framework\Deprecation\BCChange\PropertyTypeWidening;
use Shopware\Core\Framework\Log\Package;

/**
 * Applies the announced type widening to property reads.
 *
 * @internal
 */
#[Package('framework')]
class FuturePropertyTypeExtension implements ExpressionTypeResolverExtension
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
        private readonly TypeStringResolver $typeStringResolver,
    ) {
    }

    public function getType(Expr $expr, Scope $scope): ?Type
    {
        if (($expr instanceof PropertyFetch || $expr instanceof StaticPropertyFetch) && $expr->name instanceof Identifier) {
            foreach ($this->propertyClasses($expr, $scope) as $class) {
                $native = $class->getNativeReflection();
                if (!$native->hasProperty($expr->name->toString())) {
                    continue;
                }

                foreach ($native->getProperty($expr->name->toString())->getAttributes(PropertyTypeWidening::class) as $attribute) {
                    /** @var PropertyTypeWidening $widening */
                    $widening = $attribute->newInstance();

                    return $this->resolveType($widening->newType);
                }
            }
        }

        return null;
    }

    /**
     * @return list<ClassReflection>
     */
    private function propertyClasses(PropertyFetch|StaticPropertyFetch $fetch, Scope $scope): array
    {
        if ($fetch instanceof PropertyFetch) {
            return $scope->getType($fetch->var)->getObjectClassReflections();
        }
        if ($fetch->class instanceof Name) {
            $className = $scope->resolveName($fetch->class);

            return $this->reflectionProvider->hasClass($className) ? [$this->reflectionProvider->getClass($className)] : [];
        }

        return [];
    }

    private function resolveType(string $type): ?Type
    {
        try {
            return $this->typeStringResolver->resolve($type);
        } catch (\Throwable) {
            return null;
        }
    }
}
