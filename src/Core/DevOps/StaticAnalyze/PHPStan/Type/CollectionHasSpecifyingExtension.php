<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Type;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\MethodTypeSpecifyingExtension;
use PHPStan\Type\NullType;
use PHPStan\Type\TypeCombinator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @internal
 */
#[Package('core')]
class CollectionHasSpecifyingExtension implements MethodTypeSpecifyingExtension, TypeSpecifierAwareExtension
{
    private TypeSpecifier $typeSpecifier;

    public function getClass(): string
    {
        return Collection::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection, MethodCall $node, TypeSpecifierContext $context): bool
    {
        $declaringClass = $methodReflection->getDeclaringClass();

        return (
            $declaringClass->getName() === Collection::class
            || $declaringClass->isSubclassOf(Collection::class)
        )
            && $methodReflection->getName() === 'has' && !$context->null();
    }

    public function specifyTypes(
        MethodReflection $methodReflection,
        MethodCall $node,
        Scope $scope,
        TypeSpecifierContext $context
    ): SpecifiedTypes {
        $getExpr = new MethodCall($node->var, 'get', $node->args);

        $getterTypes = $this->typeSpecifier->create(
            $getExpr,
            TypeCombinator::removeNull($scope->getType($getExpr)),
            $context,
            false, // TODO NEXT-39412 Remove with update to PHPStan 2.0
            $scope
        );

        return $getterTypes->unionWith(
            $this->typeSpecifier->create(
                $getExpr,
                new NullType(),
                $context->negate(),
                false, // TODO NEXT-39412 Remove with update to PHPStan 2.0
                $scope
            )
        );
    }

    public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
    {
        $this->typeSpecifier = $typeSpecifier;
    }
}
