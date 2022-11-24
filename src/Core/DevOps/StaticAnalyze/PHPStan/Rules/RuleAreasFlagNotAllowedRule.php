<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;

/**
 * @package core
 * @implements Rule<MethodCall>
 *
 * @internal
 */
class RuleAreasFlagNotAllowedRule implements Rule
{
    private ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->isTestClass($scope)) {
            return [];
        }

        if (!$node->name instanceof Identifier) {
            return [];
        }

        if ((string) $node->name !== 'addFlags') {
            return [];
        }

        $class = $scope->getClassReflection();

        if ($class === null) {
            return [];
        }

        foreach ($node->getArgs() as $arg) {
            if ($this->resolveClassName($arg->value) !== RuleAreas::class) {
                continue;
            }

            if ($class->getName() !== RuleDefinition::class && !$class->isSubclassOf(RuleDefinition::class)) {
                return [
                    'RuleAreas flag may only be added within the scope of RuleDefinition',
                ];
            }

            $fieldClassName = $this->resolveClassName($node->var);

            if (!$fieldClassName || !$this->reflectionProvider->hasClass($fieldClassName)) {
                continue;
            }

            $mockedClass = $this->reflectionProvider->getClass($fieldClassName);
            if (!$mockedClass->isSubclassOf(AssociationField::class)) {
                return [
                    'RuleAreas flag may only be added on instances of AssociationField',
                ];
            }
        }

        return [];
    }

    private function resolveClassName(Node $node): ?string
    {
        switch (true) {
            case $node instanceof New_:
                if ($node->class instanceof Name) {
                    return (string) $node->class;
                }

                return null;
            default:
                return null;
        }
    }

    private function isTestClass(Scope $node): bool
    {
        if ($node->getClassReflection() === null) {
            return false;
        }

        $namespace = $node->getClassReflection()->getName();

        if (!\str_contains($namespace, 'Shopware\\Tests\\Unit\\') && !\str_contains($namespace, 'Shopware\\Tests\\Migration\\')) {
            return false;
        }

        if ($node->getClassReflection()->getParentClass() === null) {
            return false;
        }

        return $node->getClassReflection()->getParentClass()->getName() === TestCase::class;
    }
}
