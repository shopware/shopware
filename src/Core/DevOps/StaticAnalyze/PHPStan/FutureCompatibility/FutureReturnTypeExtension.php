<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\FutureCompatibility;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ExpressionTypeResolverExtension;
use PHPStan\Type\Type;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeWidening;
use Shopware\Core\Framework\Log\Package;

/**
 * Opt-in future-compatibility analysis (see future-compatibility.neon): calls to methods
 * whose return type is announced to widen (e.g. become nullable) are typed with the
 * announced type, so the surrounding code is analyzed against the union of the current
 * and the future contract and callers handle the new values ahead of the next major.
 *
 * @internal
 */
#[Package('framework')]
class FutureReturnTypeExtension implements ExpressionTypeResolverExtension
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
        private readonly AnnouncedTypeResolver $typeResolver,
    ) {
    }

    public function getType(Expr $expr, Scope $scope): ?Type
    {
        if ($expr instanceof MethodCall && $expr->name instanceof Identifier) {
            foreach ($scope->getType($expr->var)->getObjectClassReflections() as $classReflection) {
                $type = $this->announcedReturnType($classReflection->getNativeReflection(), $expr->name->toString());
                if ($type !== null) {
                    return $type;
                }
            }

            return null;
        }

        if ($expr instanceof StaticCall && $expr->name instanceof Identifier && $expr->class instanceof Name) {
            $className = $scope->resolveName($expr->class);
            if (!$this->reflectionProvider->hasClass($className)) {
                return null;
            }

            return $this->announcedReturnType($this->reflectionProvider->getClass($className)->getNativeReflection(), $expr->name->toString());
        }

        return null;
    }

    /**
     * @param \ReflectionClass<object> $class
     */
    private function announcedReturnType(\ReflectionClass $class, string $methodName): ?Type
    {
        if (!$class->hasMethod($methodName)) {
            return null;
        }

        $method = $class->getMethod($methodName);
        foreach ($method->getAttributes() as $attribute) {
            if ($attribute->getName() !== ReturnTypeWidening::class) {
                continue;
            }

            $arguments = $attribute->getArguments();
            $newType = $arguments['newType'] ?? $arguments[1] ?? null;
            if (!\is_string($newType)) {
                return null;
            }

            return $this->typeResolver->resolve($newType, $method->getDeclaringClass()->getName());
        }

        return null;
    }
}
