<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Type;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\MethodTypeSpecifyingExtension;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class FieldIsSpecifyingExtension implements MethodTypeSpecifyingExtension, TypeSpecifierAwareExtension
{
    private TypeSpecifier $typeSpecifier;

    public function getClass(): string
    {
        return Field::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection, MethodCall $node, TypeSpecifierContext $context): bool
    {
        $declaringClass = $methodReflection->getDeclaringClass();

        if ((
            $declaringClass->getName() !== Field::class
                || !$declaringClass->is(Field::class)
        ) || $methodReflection->getName() !== 'is' || $context->null()) {
            return false;
        }

        $flagClass = $node->args[0];
        // getFlag does not support variadic arguments, therefore, the first argument is always an Arg
        \assert($flagClass instanceof Arg);
        $value = $flagClass->value;

        return
            // case for $field->is('Some\Flag\Class')
            $value instanceof String_
            // case for $field->is(Some\Flag\Class::class), more complex cases are not supported
            || (
                $value instanceof ClassConstFetch
                && $value->name instanceof Identifier
                && $value->name->toString() === 'class'
                && $value->class instanceof Name
            );
    }

    public function specifyTypes(
        MethodReflection $methodReflection,
        MethodCall $node,
        Scope $scope,
        TypeSpecifierContext $context
    ): SpecifiedTypes {
        $getExpr = new MethodCall($node->var, 'getFlag', $node->args);

        $flagClass = $node->args[0];
        // getFlag does not support variadic arguments, therefore, the first argument is always an Arg
        \assert($flagClass instanceof Arg);
        $value = $flagClass->value;
        // value was checked in isMethodSupported()
        \assert($value instanceof String_ || $value instanceof ClassConstFetch);

        if ($value instanceof String_) {
            $className = $value->value;
        } else {
            // value->class was checked in isMethodSupported()
            \assert($value->class instanceof Name);
            $className = $value->class->toString();
        }

        $getterTypes = $this->typeSpecifier->create(
            $getExpr,
            new ObjectType($className),
            $context,
            $scope
        );

        return $getterTypes->unionWith(
            $this->typeSpecifier->create(
                $getExpr,
                new NullType(),
                $context->negate(),
                $scope
            )
        );
    }

    public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
    {
        $this->typeSpecifier = $typeSpecifier;
    }
}
