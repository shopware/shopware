<?php

declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class NameConstantEntityDefinition implements Rule
{
    use InTestClassTrait;

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // Skip test classes
        if ($this->isInTestClass($scope)) {
            return [];
        }

        $classReflection = $node->getClassReflection();
        $namespace = $classReflection->getName();
        // Check the parent scope of anonymous classes if they are in a test class
        if (str_starts_with($namespace, 'AnonymousClass') && $scope->getParentScope() && $this->isInTestClass($scope->getParentScope())) {
            return [];
        }

        // Only target subclasses of EntityDefinition
        $entityDefinitionReflection = $this->reflectionProvider->getClass(EntityDefinition::class);
        if (!$classReflection->isSubclassOfClass($entityDefinitionReflection)) {
            return [];
        }

        // If the child class itself is abstract, it could be ignored
        if ($classReflection->isAbstract()) {
            return [];
        }

        // Do not check attribute and custom entities due to their generic nature
        if (str_starts_with($namespace, 'Shopware\Core\Framework\DataAbstractionLayer\Attribute')) {
            return [];
        }
        if (str_starts_with($namespace, 'Shopware\Core\System\CustomEntity\Schema\Dynamic')) {
            return [];
        }

        $entityNameConstant = $classReflection->getNativeReflection()->getReflectionConstant('ENTITY_NAME');
        if (!$entityNameConstant || !$entityNameConstant->isPublic()) {
            return [
                RuleErrorBuilder::message('EntityDefinitions must declare a public constant named "ENTITY_NAME" which contains the entity name on storage level (e.g. "product").')
                    ->identifier('shopware.missingEntityNameConstant')
                    ->build(),
            ];
        }

        return [];
    }
}
