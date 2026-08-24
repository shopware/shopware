<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesReadonly;
use Shopware\Core\Framework\Deprecation\BCChange\PropertyTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\PropertyTypeWidening;
use Shopware\Core\Framework\Deprecation\BCChange\VisibilityChange;
use Shopware\Core\Framework\Log\Package;

/**
 * Detects subclass property redeclarations incompatible with announced Core property changes.
 *
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class FuturePropertyExtensionRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        $native = $class->getNativeReflection();
        $errors = [];

        foreach ($class->getParents() as $parent) {
            foreach ($parent->getNativeReflection()->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() !== $parent->getName()
                    || !$native->hasProperty($property->getName())
                    || $native->getProperty($property->getName())->getDeclaringClass()->getName() !== $native->getName()
                ) {
                    continue;
                }

                foreach ($property->getAttributes() as $attribute) {
                    $name = $attribute->getName();
                    $arguments = $attribute->getArguments();
                    $version = $arguments['version'] ?? $arguments[0] ?? '?';
                    if (!\is_string($version)
                        || !\in_array($name, [BecomesReadonly::class, PropertyTypeNarrowing::class, PropertyTypeWidening::class], true)
                        && !($name === VisibilityChange::class && ($arguments['newVisibility'] ?? $arguments[1] ?? null) === 'private')
                    ) {
                        continue;
                    }

                    $errors[] = RuleErrorBuilder::message(\sprintf('Property "%s::$%s" redeclares "%s::$%s", which has an incompatible property change in %s. Stop redeclaring it; there is no forward-compatible declaration.', $class->getDisplayName(), $property->getName(), $parent->getDisplayName(), $property->getName(), $version))
                        ->identifier('shopware.futureIncompatibility.propertyRedeclaration')
                        ->build();
                }
            }
        }

        return $errors;
    }
}
