<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\CompositeListingProcessor;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements Rule<InClassNode>
 */
#[Package('core')]
class AbstractClassUsageRule implements Rule
{
    use InTestClassTrait;

    private const SKIP = [
        CompositeListingProcessor::class,
    ];

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->isInTestClass($scope) || !$scope->isInClass()) {
            return [];
        }

        $class = $scope->getClassReflection();
        if (!$class->hasConstructor()) {
            return [];
        }

        $constructors = $class->getConstructor()->getVariants();
        $errors = [];
        foreach ($constructors as $constructor) {
            foreach ($constructor->getParameters() as $parameter) {
                foreach ($parameter->getType()->getObjectClassReflections() as $parameterClass) {
                    if (\in_array($parameterClass->getName(), self::SKIP, true)) {
                        continue;
                    }

                    if (!$parameterClass->hasMethod('getDecorated')) {
                        continue;
                    }

                    if ($parameterClass->isAbstract()) {
                        continue;
                    }

                    $errors[] = sprintf('Decoration error: Parameter %s $%s of %s is using the decoration pattern, but non-abstract constructor parameter is used.', $parameterClass->getName(), $parameter->getName(), $class->getName());
                }
            }
        }

        return $errors;
    }
}
