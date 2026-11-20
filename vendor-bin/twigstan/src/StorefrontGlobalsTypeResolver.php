<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ExpressionTypeResolverExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use TwigStan\PHPStan\DynamicReturnType\GetGlobalsReturnType;

/**
 * @internal
 */
#[Package('framework')]
final class StorefrontGlobalsTypeResolver implements ExpressionTypeResolverExtension
{
    public function __construct(private readonly ?GetGlobalsReturnType $globals = null)
    {
    }

    public function getType(Expr $expr, Scope $scope): ?Type
    {
        if ($this->globals === null) {
            return null;
        }

        $call = $this->getGlobalsCall($expr);
        if ($call === null || !(new ObjectType(Environment::class))->isSuperTypeOf($scope->getType($call->var))->yes()) {
            return null;
        }

        $method = $scope->getMethodReflection($scope->getType($call->var), 'getGlobals');
        if ($method === null) {
            return null;
        }

        $arrays = $this->globals->getTypeFromMethodCall($method, $call, $scope)->getConstantArrays();
        if (\count($arrays) !== 1) {
            return null;
        }

        $globals = $arrays[0];
        $builder = ConstantArrayTypeBuilder::createFromConstantArray($globals);
        foreach (StorefrontRenderContextCollector::getGlobalTypes() as $name => $type) {
            $key = new ConstantStringType($name);
            if (!$globals->hasOffsetValueType($key)->yes()) {
                $builder->setOffsetValueType($key, $type, optional: true);
            }
        }

        $arrays = $builder->getArray()->getConstantArrays();
        $contexts = $scope->getType(new Expr\Variable('context'))->getConstantArrays();
        if (\count($arrays) !== 1 || $contexts === []) {
            return null;
        }

        $globals = $arrays[0];
        $types = [];
        foreach ($contexts as $context) {
            $builder = ConstantArrayTypeBuilder::createFromConstantArray($context);
            foreach ($globals->getKeyTypes() as $index => $key) {
                $hasKey = $context->hasOffsetValueType($key);
                if ($hasKey->yes()) {
                    continue;
                }

                $type = $globals->getOffsetValueType($key);
                if ($hasKey->maybe()) {
                    $type = TypeCombinator::union($type, $context->getOffsetValueType($key));
                }

                // Locals override globals; request-dependent globals stay optional without a guaranteed request.
                $builder->setOffsetValueType($key, $type, $globals->isOptionalKey($index));
            }
            $types[] = $builder->getArray();
        }

        return TypeCombinator::union(...$types);
    }

    private function getGlobalsCall(Expr $expr): ?Expr\MethodCall
    {
        if ($expr instanceof Expr\AssignOp\Plus && $expr->var instanceof Expr\Variable && $expr->var->name === 'context') {
            $call = $expr->expr;
        } elseif ($expr instanceof Expr\FuncCall
            && $expr->name instanceof Name
            && $expr->name->toLowerString() === 'array_merge'
            && \count($expr->getArgs()) === 2
            && $expr->getArgs()[1]->value instanceof Expr\Variable
            && $expr->getArgs()[1]->value->name === 'context'
        ) {
            $call = $expr->getArgs()[0]->value;
        } else {
            return null;
        }

        return $call instanceof Expr\MethodCall && $call->name instanceof Identifier && $call->name->toLowerString() === 'getglobals'
            ? $call
            : null;
    }
}
