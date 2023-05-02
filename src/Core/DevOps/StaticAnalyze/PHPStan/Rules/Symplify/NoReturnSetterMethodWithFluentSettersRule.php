<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Symplify;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Type\ObjectType;
use PHPStan\Type\VoidType;
use Shopware\Core\Framework\Log\Package;
use Symplify\PHPStanRules\Rules\NoReturnSetterMethodRule;

/**
 * Decorator for the symplify rule to skip fluent setters, could not be added to the rule set directly: https://github.com/symplify/phpstan-rules/pull/39
 *
 * @internal
 *
 * @implements Rule<ClassMethod>
 */
#[Package('core')]
class NoReturnSetterMethodWithFluentSettersRule implements Rule
{
    public function __construct(private NoReturnSetterMethodRule $baseRule)
    {
    }

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return [];
        }

        $classMethodName = $node->name->toString();
        if (str_starts_with($classMethodName, 'setUp')) {
            return [];
        }

        $method = $classReflection->getMethod($node->name->toString(), $scope);

        $returnType = $method->getVariants()[0]->getReturnType();

        if ($returnType->isSuperTypeOf(new VoidType())->yes()) {
            return $this->baseRule->processNode($node, $scope);
        }

        $declaringClass = new ObjectType($classReflection->getName());

        // skip if method returns instance of self
        if ($declaringClass->accepts($returnType, true)->yes()) {
            return [];
        }

        return $this->baseRule->processNode($node, $scope);
    }
}
