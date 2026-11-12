<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('framework')]
final class StructGetVarsReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Struct::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'getVars'
            && $methodReflection->getDeclaringClass()->getName() === Struct::class;
    }

    /**
     * @param Scope&DependencyTracker $scope
     */
    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
    {
        $types = [];
        foreach ($scope->getType($methodCall->var)->getObjectClassReflections() as $class) {
            if (!$class->hasNativeMethod('getVars') || !$this->isMethodSupported($class->getNativeMethod('getVars'))) {
                return null;
            }
            $scope->trackClassDependency($class->getName());
            $builder = ConstantArrayTypeBuilder::createEmpty();
            // Structs can carry additional properties supplied by subclasses or dynamic assignment.
            $builder->makeUnsealed(new StringType(), new MixedType());
            foreach ($class->getNativeReflection()->getProperties() as $property) {
                if ($property->isStatic() || ($property->isPrivate() && $property->getDeclaringClass()->getName() !== Struct::class)) {
                    continue;
                }
                $reflection = $class->getNativeProperty($property->getName());
                if (!$reflection->isReadable()) {
                    continue;
                }
                $builder->setOffsetValueType(
                    new ConstantStringType($property->getName()),
                    $reflection->getReadableType(),
                    !$property->hasDefaultValue() && !$reflection->isVirtual()->yes(),
                );
            }
            $types[] = $builder->getArray();
        }

        return $types !== [] ? TypeCombinator::union(...$types) : null;
    }
}
