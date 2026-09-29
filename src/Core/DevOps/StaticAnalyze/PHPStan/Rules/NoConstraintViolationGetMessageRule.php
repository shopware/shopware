<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * @internal
 *
 * @implements Rule<MethodCall>
 */
#[Package('framework')]
class NoConstraintViolationGetMessageRule implements Rule
{
    private const SHOPWARE_STOREFRONT_CONTROLLER = 'Shopware\\Storefront\\Controller';
    private const MESSAGE = 'Do not use ConstraintViolationInterface::getMessage(). Use getCode() and translate it through the Shopware translator.';

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return [];
        }

        if ($node->name->toString() !== 'getMessage') {
            return [];
        }

        $classReflection = $scope->getClassReflection();

        if ($classReflection === null || !str_contains($classReflection->getName(), self::SHOPWARE_STOREFRONT_CONTROLLER)) {
            return [];
        }

        $violationType = TypeCombinator::removeNull($scope->getType($node->var));

        if (!(new ObjectType(ConstraintViolationInterface::class))->isSuperTypeOf($violationType)->yes()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::MESSAGE)
                ->identifier('shopware.constraintViolationGetMessage')
                ->build(),
        ];
    }
}
